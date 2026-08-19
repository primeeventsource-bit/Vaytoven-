<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Throwable;

/**
 * Finds out which SMTP settings actually authenticate.
 *
 * Exists because "it doesn't work" is not a diagnosis. A rejected password and
 * a wrong hostname and a blocked port all present as one unhelpful failure, and
 * changing the live environment to test each guess means several deploys and a
 * period where mail is configured wrongly on purpose.
 *
 * This tries the combinations against a password given on the command line,
 * touching no configuration and storing nothing. Only when a row says OK is
 * there any reason to put the value on the environment.
 *
 * The password is never printed, never logged and never persisted. It is
 * passed as an option so it stays out of the codebase; it will appear in the
 * shell history of whoever runs this, which is the trade for not having to
 * deploy a guess.
 */
class DiagnoseMail extends Command
{
    protected $signature = 'mail:diagnose
                            {--password= : Password to test. Defaults to the configured one.}
                            {--username= : Mailbox to authenticate as. Defaults to MAIL_USERNAME.}
                            {--host= : Test only this host, instead of the built-in list.}
                            {--port=465 : Port to use with --host.}';

    protected $description = 'Test SMTP hosts and ports to find which one authenticates';

    /**
     * Every combination worth trying.
     *
     * Transactional providers come first because that is what application
     * mail should use: a mailbox has a few hundred sends a day, no bounce
     * handling and no delivery log, and a spam complaint about marketing
     * mail lands on the inbox the business is run from.
     */
    private const CANDIDATES = [
        ['smtp.resend.com', 465, true,  'Resend'],
        ['smtp.resend.com', 587, false, 'Resend (STARTTLS)'],
        ['smtp.titan.email', 465, true,  'Titan, direct'],
        ['smtp.titan.email', 587, false, 'Titan, direct (STARTTLS)'],
        ['smtpout.secureserver.net', 465, true,  'GoDaddy relay'],
        ['smtpout.secureserver.net', 587, false, 'GoDaddy relay (STARTTLS)'],
    ];

    public function handle(): int
    {
        $username = (string) ($this->option('username') ?: config('mail.mailers.smtp.username'));
        $password = (string) ($this->option('password') ?: config('mail.mailers.smtp.password'));

        if ($username === '' || $password === '') {
            $this->error('Need a username and a password. Pass --password= to test one without storing it.');

            return self::FAILURE;
        }

        $this->line('Authenticating as '.$username);
        $this->line('Password length '.strlen($password).' — the value itself is never shown or stored.');
        $this->newLine();

        $rows = [];
        $success = null;

        $candidates = self::CANDIDATES;

        if ($host = $this->option('host')) {
            $port = (int) $this->option('port');
            // 465 is implicit TLS; anything else is assumed STARTTLS,
            // which is the convention every provider follows.
            $candidates = [[$host, $port, $port === 465, 'supplied']];
        }

        foreach ($candidates as [$host, $port, $tls, $label]) {
            $result = $this->attempt($host, $port, $tls, $username, $password);

            $rows[] = [$label, $host, $port, $tls ? 'SSL' : 'STARTTLS', $result];

            if ($result === 'OK' && ! $success) {
                $success = [$host, $port, $tls];
            }
        }

        $this->table(['Provider', 'Host', 'Port', 'Encryption', 'Result'], $rows);

        if (! $success) {
            $this->error('None of them authenticated.');
            $this->newLine();
            $this->line('That rules out the hostname and the port, which leaves the mailbox:');
            $this->line('  1. Sign in at titan.email with this address and password. If that fails,');
            $this->line('     the password is simply wrong and nothing here will work.');
            $this->line('  2. In Titan, check the mailbox allows external clients (IMAP/SMTP access).');
            $this->line('     A mailbox that only works in webmail authenticates exactly like this.');
            $this->line('  3. If the address forwards to another mailbox, authenticate as that one');
            $this->line('     instead and keep MAIL_FROM_ADDRESS as contact@vaytoven.com.');

            return self::FAILURE;
        }

        [$host, $port, $tls] = $success;

        $this->info('Authenticated.');
        $this->newLine();
        $this->line('Set these on the environment, then redeploy:');
        $this->line('  MAIL_MAILER=smtp');
        $this->line('  MAIL_HOST='.$host);
        $this->line('  MAIL_PORT='.$port);
        $this->line('  MAIL_SCHEME='.($tls ? 'smtps' : 'smtp'));
        $this->line('  MAIL_USERNAME='.$username);
        $this->line('  MAIL_PASSWORD=<the password you just tested>');
        $this->line('  MAIL_FROM_ADDRESS=contact@vaytoven.com');

        return self::SUCCESS;
    }

    /**
     * One connection attempt.
     *
     * Only the SMTP conversation is exercised — no message is sent, so running
     * this repeatedly cannot spam anyone or count against a sending quota.
     */
    private function attempt(string $host, int $port, bool $tls, string $username, string $password): string
    {
        try {
            $transport = new EsmtpTransport($host, $port, $tls);
            $transport->setUsername($username);
            $transport->setPassword($password);

            $stream = $transport->getStream();

            if ($stream instanceof SocketStream) {
                $stream->setTimeout(12);
            }

            $transport->start();
            $transport->stop();

            return 'OK';
        } catch (Throwable $e) {
            $message = $e->getMessage();

            return match (true) {
                str_contains($message, '535')          => 'rejected (535)',
                str_contains($message, 'Connection')   => 'unreachable',
                str_contains($message, 'timed out')    => 'timed out',
                default                                => mb_substr($message, 0, 40),
            };
        }
    }
}
