<?php
// ใช้ร่วมกันเฉพาะ Rate limit ของหน้า Login
// ไม่เริ่ม Session หรือเชื่อมฐานข้อมูล
class LoginRateLimit
{
    const MAX_ATTEMPTS = 5;
    const LOCK_SECONDS = 300;

    private $handle = null;
    private $directory;
    private $file;
    private $data = ['attempts' => 0, 'last_time' => 0];

    public function __construct($ip)
    {
        if (!is_string($ip)
            || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            throw new RuntimeException('Invalid login limit address');
        }

        $this->directory = __DIR__ . '/uploads';

        if (!is_dir($this->directory)
            || !is_writable($this->directory)) {
            throw new RuntimeException('Login limit storage unavailable');
        }

        $this->file = $this->directory
            . '/login_attempts_' . md5($ip) . '.txt';

        // ใช้ไฟล์ล็อกแยกที่ไม่ถูกลบเมื่อรีเซ็ตตัวนับ
        $this->handle = @fopen($this->file . '.lock', 'c+b');

        if ($this->handle === false) {
            $this->handle = null;
            throw new RuntimeException('Login limit lock unavailable');
        }

        try {
            $deadline = microtime(true) + 2;

            while (!@flock($this->handle, LOCK_EX | LOCK_NB)) {
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('Login limit busy');
                }
                usleep(20000);
            }

            if (is_file($this->file)) {
                $json = @file_get_contents($this->file);
                $data = is_string($json)
                    ? json_decode($json, true) : null;

                if (!is_array($data)
                    || !is_int($data['attempts'] ?? null)
                    || !is_int($data['last_time'] ?? null)
                    || $data['attempts'] < 0
                    || $data['last_time'] < 0
                    || $data['last_time'] > time()
                    || ($data['attempts'] > 0
                        && $data['last_time'] === 0)) {
                    throw new RuntimeException('Login limit data invalid');
                }

                $this->data = $data;
            } elseif (file_exists($this->file)) {
                throw new RuntimeException('Login limit path invalid');
            }
        } catch (Throwable $e) {
            $this->close();
            throw $e;
        }
    }

    public function attempts()
    {
        return min(self::MAX_ATTEMPTS, $this->data['attempts']);
    }

    public function remaining()
    {
        if ($this->data['attempts'] < self::MAX_ATTEMPTS) {
            return 0;
        }

        return max(
            0,
            self::LOCK_SECONDS - (time() - $this->data['last_time'])
        );
    }

    public function resetExpired()
    {
        if ($this->data['attempts'] >= self::MAX_ATTEMPTS
            && $this->remaining() === 0) {
            $this->reset();
        }
    }

    public function recordFailure()
    {
        if ($this->remaining() > 0) {
            return;
        }

        $this->save([
            'attempts' => min(
                self::MAX_ATTEMPTS,
                $this->data['attempts'] + 1
            ),
            'last_time' => time(),
        ]);
    }

    public function reset()
    {
        $this->save(['attempts' => 0, 'last_time' => 0]);
    }

    private function save($data)
    {
        if (!is_resource($this->handle)) {
            throw new RuntimeException('Login limit lock missing');
        }

        $json = json_encode($data);
        $temp = @tempnam(
            $this->directory,
            'login_attempts_tmp_'
        );

        if (!is_string($json) || $temp === false) {
            if (is_string($temp)) {
                @unlink($temp);
            }
            throw new RuntimeException('Login limit write unavailable');
        }

        try {
            if (realpath(dirname($temp))
                    !== realpath($this->directory)
                || @file_put_contents($temp, $json)
                    !== strlen($json)
                || !@rename($temp, $this->file)) {
                throw new RuntimeException('Login limit write failed');
            }

            $this->data = $data;
        } finally {
            if (is_file($temp)) {
                @unlink($temp);
            }
        }
    }

    public function close()
    {
        if (is_resource($this->handle)) {
            @flock($this->handle, LOCK_UN);
            @fclose($this->handle);
        }
        $this->handle = null;
    }

    public function __destruct()
    {
        $this->close();
    }
}