<?php
/**
 * @var \App\Core\View\Template $this
 * @var string                  $name
 * @var string                  $loginUrl
 * @var string                  $resetUrl
 */
$this->layout('emails/layout');
?>
<h1 style="margin:0 0 16px;font-size:21px;line-height:1.3;">Vous avez déjà un compte</h1>
<p style="margin:0 0 12px;">Bonjour <?= e($name) ?>,</p>
<p style="margin:0;">Quelqu'un (probablement vous) a essayé de créer un compte avec cette adresse, qui est déjà inscrite. Vous pouvez simplement vous connecter.</p>
<?= $this->insert('emails/button', ['url' => $loginUrl, 'label' => 'Se connecter']) ?>
<p style="margin:24px 0 0;font-size:13px;color:#5b6168;">Mot de passe oublié ? <a href="<?= e($resetUrl) ?>" style="color:#0b7a4b;">Réinitialisez-le ici</a>. Si vous n'êtes pas à l'origine de cette demande, ignorez ce message.</p>
