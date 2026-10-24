<?php
/**
 * @var \App\Core\View\Template $this
 * @var string                  $name
 * @var string                  $url
 */
$this->layout('emails/layout');
?>
<h1 style="margin:0 0 16px;font-size:21px;line-height:1.3;">Réinitialisation du mot de passe</h1>
<p style="margin:0 0 12px;">Bonjour <?= e($name) ?>,</p>
<p style="margin:0;">Une réinitialisation de mot de passe a été demandée pour votre compte. Ce lien est valable <strong>1 heure</strong> et ne peut servir qu'une fois.</p>
<?= $this->insert('emails/button', ['url' => $url, 'label' => 'Choisir un nouveau mot de passe']) ?>
<p style="margin:24px 0 0;font-size:13px;color:#5b6168;">Vous n'avez rien demandé ? Ignorez ce message : votre mot de passe actuel reste valable.</p>
