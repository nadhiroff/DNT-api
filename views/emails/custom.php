<?php
/**
 * Modèle d'e-mail personnalisé : titre, paragraphes (texte brut échappé), bouton.
 *
 * @var \App\Core\View\Template $this
 * @var string                  $heading
 * @var list<string>            $paragraphs
 * @var string                  $buttonLabel
 * @var string                  $url
 */
$this->layout('emails/layout');
?>
<h1 style="margin:0 0 16px;font-size:21px;line-height:1.3;"><?= e($heading) ?></h1>
<?php foreach ($paragraphs as $i => $paragraph): ?>
<p style="margin:<?= e($i === 0 ? '0' : '14px 0 0') ?>;white-space:pre-line;"><?= e($paragraph) ?></p>
<?php endforeach; ?>
<?php if ($url !== '' && $buttonLabel !== ''): ?>
<?= $this->insert('emails/button', ['url' => $url, 'label' => $buttonLabel]) ?>
<?php endif; ?>
