<?php
/**
 * @var \App\Core\View\Template $this
 * @var string                  $url
 * @var string                  $label
 */
?>
<table role="presentation" cellspacing="0" cellpadding="0" style="margin:28px 0;"><tr><td style="border-radius:9px;background:#3ecf8e;">
<a href="<?= e($url) ?>" style="display:inline-block;padding:13px 24px;font-size:15px;font-weight:600;color:#04130b;text-decoration:none;border-radius:9px;"><?= e($label) ?></a>
</td></tr></table>
<p style="margin:0;font-size:13px;color:#5b6168;">Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br><a href="<?= e($url) ?>" style="color:#0b7a4b;word-break:break-all;"><?= e($url) ?></a></p>
