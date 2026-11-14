<?php

namespace App\Services;

/**
 * Minimal read-only WebSocket client for Voicebip's live call event
 * stream (GET /v1/calls/{id}/stream upgraded to wss://). Pure PHP over
 * stream_socket_client + openssl — no extra packages, safe on shared
 * hosting.
 *
 * Unlike the SSE transcript stream (which flushes nothing until the call
 * ends), this delivers events LIVE: call.agent_response (greeting TTS
 * starting), call.idle_silence (callee silent since the notice), call.
 * transcription, call.barge_in, call.completed — then closes with a
 * normal close frame.
 */
class VoicebipEventStream
{
    /** @var resource|null */
    private $stream;

    private function __construct($stream)
    {
        $this->stream = $stream;
    }

    /**
     * Open the stream for one call. Returns null on any failure —
     * callers fall back to timed/webhook hang-up, never throws.
     */
    public static function open(string $callId): ?self
    {
        try {
            $base = rtrim((string) config('services.voicebip.endpoint'), '/');
            $host = parse_url($base, PHP_URL_HOST);
            $tlsPath = (string) parse_url($base, PHP_URL_PATH);
            $port = parse_url($base, PHP_URL_PORT) ?: 443;

            if (! is_string($host) || $host === '') {
                return null;
            }

            $path = $tlsPath.'/calls/'.$callId.'/stream?token='
                .rawurlencode((string) config('services.voicebip.api_key'));

            $stream = @stream_socket_client('tls://'.$host.':'.$port, $errno, $errstr, 8, STREAM_CLIENT_CONNECT);

            if (! $stream) {
                return null;
            }

            $key = base64_encode(random_bytes(16));
            $request = "GET {$path} HTTP/1.1\r\n"
                ."Host: {$host}\r\n"
                ."Upgrade: websocket\r\n"
                ."Connection: Upgrade\r\n"
                ."Sec-WebSocket-Key: {$key}\r\n"
                ."Sec-WebSocket-Version: 13\r\n"
                ."\r\n";

            stream_set_timeout($stream, 5);
            fwrite($stream, $request);

            $headers = '';
            while (! str_contains($headers, "\r\n\r\n")) {
                $chunk = fread($stream, 1024);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $headers .= $chunk;
            }

            if (! preg_match('#^HTTP/1\.1 101#', $headers)) {
                @fclose($stream);

                return null;
            }

            return new self($stream);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Read the next event frame. Returns the decoded JSON event, or null
     * when the deadline passes, the server closes, or the stream breaks.
     * Transparently answers pings; ignores non-text frames.
     *
     * @return array<string, mixed>|null
     */
    public function read(float $deadline): ?array
    {
        if (! is_resource($this->stream)) {
            return null;
        }

        while (true) {
            if (microtime(true) >= $deadline) {
                return null;
            }

            $remaining = $deadline - microtime(true);
            $read = [$this->stream];
            $write = $except = null;
            $sec = (int) $remaining;
            $usec = (int) (($remaining - $sec) * 1_000_000);

            if (! @stream_select($read, $write, $except, max($sec, 0), max($usec, 1))) {
                continue;
            }

            $header = $this->readBytes(2);

            if ($header === null) {
                return null;
            }

            $opcode = ord($header[0]) & 0x0F;
            $b1 = ord($header[1]);
            $masked = ($b1 & 0x80) !== 0;
            $length = $b1 & 0x7F;

            if ($length === 126) {
                $ext = $this->readBytes(2);
                if ($ext === null) {
                    return null;
                }
                $length = unpack('n', $ext)[1];
            } elseif ($length === 127) {
                $ext = $this->readBytes(8);
                if ($ext === null) {
                    return null;
                }
                $length = unpack('J', $ext)[1];
            }

            $maskKey = null;
            if ($masked) {
                $maskKey = $this->readBytes(4);
                if ($maskKey === null) {
                    return null;
                }
            }

            $payload = $length > 0 ? $this->readBytes($length) : '';

            if ($payload === null) {
                return null;
            }

            if ($maskKey !== null) {
                $unmasked = '';
                for ($i = 0, $n = strlen($payload); $i < $n; $i++) {
                    $unmasked .= $payload[$i] ^ $maskKey[$i % 4];
                }
                $payload = $unmasked;
            }

            if ($opcode === 0x9) { // ping -> pong (client frames must be masked)
                $this->writeFrame($payload, 0xA);

                continue;
            }

            if ($opcode === 0x8) { // server close
                return null;
            }

            if ($opcode !== 0x1 && $opcode !== 0x0) { // ignore binary/pong
                continue;
            }

            $event = json_decode($payload, true);

            if (! is_array($event)) {
                continue;
            }

            return $event;
        }
    }

    public function close(): void
    {
        if (is_resource($this->stream)) {
            @fclose($this->stream);
        }

        $this->stream = null;
    }

    public function __destruct()
    {
        $this->close();
    }

    private function readBytes(int $count): ?string
    {
        $data = '';

        while (strlen($data) < $count && is_resource($this->stream)) {
            $chunk = @fread($this->stream, $count - strlen($data));

            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($this->stream);

                if (! ($meta['timed_out'] ?? false)) {
                    return null;
                }

                // Stall past the stream timeout: treat as dead.
                return null;
            }

            $data .= $chunk;
        }

        return strlen($data) === $count ? $data : null;
    }

    private function writeFrame(string $payload, int $opcode): bool
    {
        if (! is_resource($this->stream)) {
            return false;
        }

        $mask = random_bytes(4);
        $length = strlen($payload);
        $frame = chr(0x80 | $opcode);

        if ($length < 126) {
            $frame .= chr(0x80 | $length);
        } elseif ($length < 65536) {
            $frame .= chr(0x80 | 126).pack('n', $length);
        } else {
            $frame .= chr(0x80 | 127).pack('J', $length);
        }

        $masked = '';
        for ($i = 0; $i < $length; $i++) {
            $masked .= $payload[$i] ^ $mask[$i % 4];
        }

        return @fwrite($this->stream, $frame.$mask.$masked) !== false;
    }
}
