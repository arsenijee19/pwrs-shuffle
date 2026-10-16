<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Services;

use RuntimeException;
use Throwable;

final class MailService
{
    private float $smtpDeadline = 0.0;

    public function sendMysteryBoxLink(string $recipientEmail, string $recipientName, string $link): void
    {
        $config = $this->config();

        if (!$config['enabled']) {
            throw new RuntimeException('Email slanje nije uključeno. Otvorite Admin > Email i uključite isporuku.');
        }

        if ($config['host'] === '' || $config['username'] === '' || $config['password'] === '' || $config['from'] === '') {
            throw new RuntimeException('SMTP podešavanja nisu kompletna. Otvorite Admin > Email i proverite podatke.');
        }

        $html = $this->mysteryBoxHtml($link, $recipientName);
        $text = $this->mysteryBoxText($link, $recipientName);
        $message = $this->buildMessage(
            $config['from'],
            $config['from_name'],
            $recipientEmail,
            'Vaš PlayWorld Mystery Box link',
            $html,
            $text
        );

        $this->smtpSend(
            $config['host'],
            $config['port'],
            $config['username'],
            $config['password'],
            $config['from'],
            $recipientEmail,
            $message,
            $config['timeout']
        );
    }

    /** @param array<int,string> $links */
    public function sendMysteryBoxLinks(string $recipientEmail, string $recipientName, array $links): void
    {
        $config = $this->config();
        if (!$config['enabled']) {
            throw new RuntimeException('Email slanje nije uključeno.');
        }
        if ($config['host'] === '' || $config['username'] === '' || $config['password'] === '' || $config['from'] === '') {
            throw new RuntimeException('SMTP podešavanja nisu kompletna.');
        }

        $html = $this->mysteryBoxLinksHtml($links, $recipientName);
        $text = $this->mysteryBoxLinksText($links, $recipientName);
        $message = $this->buildMessage(
            $config['from'],
            $config['from_name'],
            $recipientEmail,
            'Vaši PlayWorld Mystery Box linkovi',
            $html,
            $text
        );

        $this->smtpSend($config['host'], $config['port'], $config['username'], $config['password'], $config['from'], $recipientEmail, $message, $config['timeout']);
    }

    public function sendTestEmail(string $recipientEmail): void
    {
        $config = $this->config();

        if (!$config['enabled']) {
            throw new RuntimeException('Email slanje nije uključeno. Otvorite Admin > Email i uključite isporuku.');
        }

        if ($config['host'] === '' || $config['username'] === '' || $config['password'] === '' || $config['from'] === '') {
            throw new RuntimeException('SMTP podešavanja nisu kompletna. Otvorite Admin > Email i proverite podatke.');
        }

        $html = '<!doctype html><html lang="sr"><body style="font-family:Arial,sans-serif;color:#121826">'
            . '<h1>PlayWorld Mystery Box</h1><p>Ovo je test email. Brevo SMTP podešavanja rade ispravno.</p>'
            . '</body></html>';
        $text = "PlayWorld Mystery Box\n\nOvo je test email. Brevo SMTP podešavanja rade ispravno.";
        $message = $this->buildMessage(
            $config['from'],
            $config['from_name'],
            $recipientEmail,
            'PlayWorld Mystery Box - test email',
            $html,
            $text
        );

        $this->smtpSend(
            $config['host'],
            $config['port'],
            $config['username'],
            $config['password'],
            $config['from'],
            $recipientEmail,
            $message,
            $config['timeout']
        );
    }

    public function mysteryBoxHtml(string $link, string $recipientName = ''): string
    {
        ob_start();
        require app_path('resources/views/emails/mystery-box.php');
        return (string) ob_get_clean();
    }

    public function mysteryBoxText(string $link, string $recipientName = ''): string
    {
        $greeting = trim($recipientName) !== '' ? 'Poštovani/a ' . trim($recipientName) . ',' : 'Poštovani,';

        return implode("\n\n", [
            $greeting,
            'u prilogu Vam šaljemo Vaš jedinstveni link za PlayWorld Mystery Box:',
            $link,
            'Otvorite link i ispratite instrukcije prikazane na stranici. Mystery Box možete otvoriti samo jednom, a rezultat nakon otvaranja nije moguće promeniti.',
            'Ako osvojite igru ili poklon, na stranici rezultata kliknite „Preuzmi nagradu“ i ostavite kontakt. Screenshot nije potreban: naš tim će Vas kontaktirati tokom radnog vremena radi isporuke.',
            'Hvala Vam na učešću i želimo Vam puno sreće!',
            'Srdačno, PlayWorld tim',
        ]);
    }

    /** @param array<int,string> $links */
    public function mysteryBoxLinksHtml(array $links, string $recipientName = ''): string
    {
        ob_start();
        require app_path('resources/views/emails/mystery-box-links.php');
        return (string) ob_get_clean();
    }

    /** @param array<int,string> $links */
    public function mysteryBoxLinksText(array $links, string $recipientName = ''): string
    {
        $greeting = trim($recipientName) !== '' ? 'Poštovani/a ' . trim($recipientName) . ',' : 'Poštovani,';
        return implode("\n\n", [
            $greeting,
            'Osvojili ste dodatne PlayWorld Mystery Box linkove. Svaki link se otvara zasebno i može se iskoristiti samo jednom.',
            implode("\n", array_map(static fn (string $link, int $index): string => ($index + 1) . '. ' . $link, $links, array_keys($links))),
            'Hvala Vam na učešću! PlayWorld tim',
        ]);
    }

    private function buildMessage(
        string $from,
        string $fromName,
        string $to,
        string $subject,
        string $html,
        string $text
    ): string {
        $boundary = '=_PlayWorld_' . bin2hex(random_bytes(12));
        $host = parse_url(url('/'), PHP_URL_HOST) ?: 'playworld.rs';
        $encodedFromName = $this->encodeHeader($fromName);

        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $encodedFromName . ' <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: ' . $this->encodeHeader($subject),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $host . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        $body = implode("\r\n", [
            '--' . $boundary,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            $text,
            '',
            '--' . $boundary,
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            $html,
            '',
            '--' . $boundary . '--',
        ]);

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    /**
     * @param resource $socket
     */
    private function smtpCommand($socket, string $command, array $expectedCodes): string
    {
        fwrite($socket, $command . "\r\n");
        $response = $this->smtpRead($socket);
        $code = (int) substr($response, 0, 3);

        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException('SMTP server je odbio zahtev. Proverite Brevo podešavanja.');
        }

        return $response;
    }

    /**
     * @param resource $socket
     */
    private function smtpRead($socket): string
    {
        $response = '';
        $deadline = $this->smtpDeadline > 0 ? $this->smtpDeadline : microtime(true) + 15;

        while (microtime(true) < $deadline) {
            $line = fgets($socket, 515);
            if ($line === false) {
                $meta = stream_get_meta_data($socket);
                if (!empty($meta['timed_out'])) {
                    continue;
                }
                break;
            }
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }

        if ($response === '') {
            throw new RuntimeException('SMTP server nije vratio odgovor u predviđenom roku.');
        }

        return $response;
    }

    private function smtpSend(
        string $host,
        int $port,
        string $username,
        string $password,
        string $from,
        string $to,
        string $message,
        int $timeout
    ): void {
        $socket = @stream_socket_client(
            'tcp://' . $host . ':' . $port,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT
        );

        if (!is_resource($socket)) {
            throw new MailDeliveryException('Nije moguće povezivanje sa SMTP serverom.', false, 'smtp_connection_failed');
        }

        $this->smtpDeadline = microtime(true) + max(3, min($timeout, 30));
        $afterData = false;
        $accepted = false;
        try {
            // stream_set_timeout applies to each read. smtpRead also uses the
            // deadline so one broken SMTP response cannot keep the request open
            // for the timeout once per SMTP command.
            stream_set_timeout($socket, 1);
            $this->expectGreeting($socket);
            $this->smtpCommand($socket, 'EHLO ' . (parse_url(url('/'), PHP_URL_HOST) ?: 'localhost'), [250]);
            $this->smtpCommand($socket, 'STARTTLS', [220]);

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('SMTP TLS konekcija nije uspela.');
            }

            $this->smtpCommand($socket, 'EHLO ' . (parse_url(url('/'), PHP_URL_HOST) ?: 'localhost'), [250]);
            $this->smtpCommand($socket, 'AUTH LOGIN', [334]);
            $this->smtpCommand($socket, base64_encode($username), [334]);
            $this->smtpCommand($socket, base64_encode($password), [235]);
            $this->smtpCommand($socket, 'MAIL FROM:<' . $from . '>', [250]);
            $this->smtpCommand($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            $this->smtpCommand($socket, 'DATA', [354]);
            $afterData = true;
            $this->smtpCommand($socket, $this->normalizeMessage($message) . "\r\n.", [250]);
            $accepted = true;
            $this->smtpCommand($socket, 'QUIT', [221]);
        } catch (MailDeliveryException $error) {
            throw $error;
        } catch (Throwable $error) {
            if ($afterData && !$accepted) {
                throw new MailDeliveryException(
                    'SMTP server nije pouzdano potvrdio prijem emaila.',
                    true,
                    'smtp_unknown'
                );
            }

            throw new MailDeliveryException($error->getMessage(), false, 'smtp_failed');
        } finally {
            $this->smtpDeadline = 0.0;
            fclose($socket);
        }
    }

    /**
     * @param resource $socket
     */
    private function expectGreeting($socket): void
    {
        $response = $this->smtpRead($socket);
        if ((int) substr($response, 0, 3) !== 220) {
            throw new RuntimeException('SMTP server nije spreman za slanje.');
        }
    }

    private function normalizeMessage(string $message): string
    {
        $message = str_replace(["\r\n", "\r"], "\n", $message);
        $message = preg_replace('/^\./m', '..', $message) ?? $message;
        return str_replace("\n", "\r\n", $message);
    }

    private function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function config(): array
    {
        $saved = (new SettingsService())->emailConfig();
        if ($saved['enabled']) {
            return $saved;
        }

        return [
            'enabled' => (bool) env_value('MAIL_ENABLED', false),
            'host' => (string) env_value('SMTP_HOST', ''),
            'port' => (int) env_value('SMTP_PORT', 587),
            'username' => (string) env_value('SMTP_USERNAME', ''),
            'password' => (string) env_value('SMTP_PASSWORD', ''),
            'from' => (string) env_value('MAIL_FROM', ''),
            'from_name' => (string) env_value('MAIL_FROM_NAME', 'PlayWorld - Mystery Box'),
            'timeout' => (int) env_value('MAIL_TIMEOUT', 15),
        ];
    }
}
