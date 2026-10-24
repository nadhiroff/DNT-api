<?php
/**
 * Gabarit des e-mails : tableaux et styles en ligne pour la compatibilité des clients mail.
 *
 * @var \App\Core\View\Template $this
 * @var string                  $appName
 * @var string                  $subject
 */
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title><?= e($subject) ?></title>
</head>
<body style="margin:0;padding:0;background:#f4f5f4;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#111315;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f5f4;padding:32px 16px;">
<tr><td align="center">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:520px;">
<tr><td style="padding:0 4px 20px;font-size:17px;font-weight:600;letter-spacing:-0.01em;">
<span style="display:inline-block;width:26px;height:26px;border-radius:7px;background:#0c0d0f;color:#3ecf8e;text-align:center;line-height:26px;font-size:15px;margin-right:8px;">N</span><?= e($appName) ?>
</td></tr>
<tr><td style="background:#ffffff;border:1px solid #e2e4e3;border-radius:14px;padding:32px;font-size:15px;line-height:1.6;">
<?= $this->section('content') ?>
</td></tr>
<tr><td style="padding:20px 4px;font-size:12px;line-height:1.5;color:#5b6168;">
Cet e-mail vous a été envoyé automatiquement par <?= e($appName) ?>. Merci de ne pas y répondre.
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
