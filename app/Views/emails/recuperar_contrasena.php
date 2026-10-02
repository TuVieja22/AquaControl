<?php // Email de "olvide mi contrasena" (lo arma Auth::enviarEmailRecuperacion). Variables: $nombre, $enlace ?>
<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;background:#0D2B24;color:#E1F5EE;padding:40px;border-radius:16px;">
    <h2 style="color:#5DCAA5;font-size:24px;margin-bottom:8px;">AquaControl</h2>
    <p style="color:rgba(159,225,203,0.7);font-size:13px;margin-bottom:32px;">Sistema Inteligente IoT</p>
    <h3 style="color:#fff;margin-bottom:12px;">Hola, <?= esc($nombre) ?></h3>
    <p style="color:rgba(159,225,203,0.75);line-height:1.7;">
        Recibimos una solicitud para restablecer la contrasena de tu cuenta.
        Haz clic en el boton a continuacion para crear una nueva contrasena.
        <br><br>
        <strong style="color:#EF9F27;">Este enlace expira en 1 hora.</strong>
    </p>
    <div style="text-align:center;margin:32px 0;">
        <a href="<?= esc($enlace, 'attr') ?>" style="background:#1D9E75;color:#fff;padding:14px 32px;border-radius:10px;text-decoration:none;font-weight:600;font-size:15px;display:inline-block;">
            Restablecer contrasena
        </a>
    </div>
    <p style="color:rgba(159,225,203,0.4);font-size:12px;line-height:1.6;">
        Si no solicitaste este cambio, ignora este correo. Tu contrasena seguira siendo la misma.<br>
        O copia este enlace en tu navegador:<br>
        <a href="<?= esc($enlace, 'attr') ?>" style="color:#5DCAA5;word-break:break-all;"><?= esc($enlace) ?></a>
    </p>
</div>
