<?php
/**
 * @var \App\Core\View\Template $this
 * @var string                  $name
 * @var string                  $url
 */
$this->layout('emails/layout');
?>
<h1 style="margin:0 0 16px;font-size:21px;line-height:1.3;">Confirmez votre adresse e-mail</h1>
<p style="margin:0 0 12px;">Bonjour <?= e($name) ?>,</p>
<p style="margin:0;">Bienvenue ! Cliquez sur le bouton ci-dessous pour activer votre compte. Ce lien est valable <strong>1 heure</strong> et ne peut servir qu'une fois.</p>
<?= $this->insert('emails/button', ['url' => $url, 'label' => 'Confirmer mon adresse']) ?>
<p style="margin:24px 0 0;font-size:13px;color:#5b6168;">Vous n'êtes pas à l'origine de cette inscription ? Ignorez simplement ce message.</p>
