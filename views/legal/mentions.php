<?php
/**
 * @var \App\Core\View\Template                                              $this
 * @var string                                                               $appName
 * @var string                                                               $appUrl
 * @var array{company: string, address: string, email: string, host: string} $legal
 */
$this->layout('legal/layout', ['pageTitle' => 'Mentions légales']);
$todo = 'à compléter dans config/env.php';
?>
<?php $this->start('body') ?>
<h2>Éditeur du service</h2>
<p><strong><?= e($legal['company']) ?></strong><br>
Adresse : <?= e($legal['address'] !== '' ? $legal['address'] : $todo) ?><br>
Contact : <?= e($legal['email'] !== '' ? $legal['email'] : $todo) ?></p>

<h2>Service</h2>
<p><?= e($appName) ?> est une plateforme de backend auto-hébergée accessible à l'adresse <?= e($appUrl) ?>. Elle fournit des bases de données, une API REST, l'authentification des utilisateurs, le stockage de fichiers et l'envoi d'e-mails.</p>

<h2>Hébergement</h2>
<p><?= e($legal['host'] !== '' ? $legal['host'] : 'Hébergeur : ' . $todo) ?></p>

<h2>Propriété intellectuelle</h2>
<p>Les éléments graphiques, textes et logiciels composant le site sont protégés. Les données que vous stockez dans vos projets restent votre propriété exclusive.</p>

<h2>Contact</h2>
<p>Pour toute question ou réclamation, écrivez à <?= e($legal['email'] !== '' ? $legal['email'] : 'l\'adresse de contact indiquée ci-dessus') ?>.</p>
<?php $this->stop() ?>
