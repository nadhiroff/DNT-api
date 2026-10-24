<?php
/**
 * @var \App\Core\View\Template $this
 * @var string                  $inviterName
 * @var string|null             $personalMessage
 * @var string                  $url
 */
$this->layout('emails/layout');
?>
<h1 style="margin:0 0 16px;font-size:21px;line-height:1.3;"><?= e($inviterName) ?> vous invite sur NovaBase</h1>
<p style="margin:0;">Vous êtes invité(e) à rejoindre NovaBase, la plateforme backend pour vos applications web et mobiles. Votre compte est prêt : il sera activé dès que vous aurez accepté l'invitation.</p>
<?php if ($personalMessage !== null && $personalMessage !== ''): ?>
<p style="margin:16px 0 0;padding:12px 16px;border-left:3px solid #3ecf8e;background:#f4f5f4;font-style:italic;color:#333;">« <?= e($personalMessage) ?> »</p>
<?php endif; ?>
<?= $this->insert('emails/button', ['url' => $url, 'label' => 'Accepter l\'invitation']) ?>
<p style="margin:24px 0 0;font-size:13px;color:#5b6168;">Ce lien est valable 7 jours et ne peut servir qu'une fois. Si vous ne connaissez pas l'expéditeur, ignorez ce message : aucun compte ne sera activé.</p>
