<?php
/**
 * @var \App\Core\View\Template $this
 * @var string                  $name
 * @var string                  $resetUrl
 */
$this->layout('emails/layout');
?>
<h1 style="margin:0 0 16px;font-size:21px;line-height:1.3;">Votre mot de passe a été modifié</h1>
<p style="margin:0 0 12px;">Bonjour <?= e($name) ?>,</p>
<p style="margin:0;">Le mot de passe de votre compte vient d'être modifié et vos autres sessions ont été fermées.</p>
<p style="margin:16px 0 0;">Si ce n'est pas vous, <a href="<?= e($resetUrl) ?>" style="color:#0b7a4b;font-weight:600;">réinitialisez immédiatement votre mot de passe</a>.</p>
