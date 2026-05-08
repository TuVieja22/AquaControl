<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Email extends BaseConfig
{
    public string $fromEmail    = '';
    public string $fromName     = '';
    public string $recipients   = '';
    public string $userAgent    = 'CodeIgniter';

    // ── FORZAR SMTP ──────────────────────────────────
    public string $protocol     = 'smtp';
    public string $mailPath     = '/usr/sbin/sendmail';

    public string $SMTPHost     = 'smtp.gmail.com';
    public string $SMTPUser     = '';
    public string $SMTPPass     = '';
    public int    $SMTPPort     = 587;
    public int    $SMTPTimeout  = 60;
    public bool   $SMTPKeepAlive = false;
    public string $SMTPCrypto   = 'tls';

    public bool   $wordWrap     = true;
    public int    $wrapChars    = 76;
    public string $mailType     = 'html';
    public string $charset      = 'UTF-8';
    public bool   $validate     = true;
    public int    $priority     = 3;
    public string $newline      = "\r\n";
    public string $CRLF         = "\r\n";
    public bool   $BCCBatchMode = false;
    public int    $BCCBatchSize = 200;
    public bool   $DSN          = false;

    public function __construct()
    {
        parent::__construct();

        $this->fromEmail  = env('email.fromEmail', 'noreply@aquacontrol.com');
        $this->fromName   = env('email.fromName',  'AquaControl');
        $this->SMTPHost   = env('email.SMTPHost',  'smtp.gmail.com');
        $this->SMTPUser   = env('email.SMTPUser',  '');
        $this->SMTPPass   = env('email.SMTPPass',  '');
        $this->SMTPPort   = (int) env('email.SMTPPort', 587);
        $this->SMTPCrypto = env('email.SMTPCrypto', 'tls');

        // Protocolo SIEMPRE smtp
        $this->protocol   = 'smtp';
    }
}