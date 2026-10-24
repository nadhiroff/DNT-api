<?php
/**
 * @var \App\Core\View\Template                                              $this
 * @var string                                                               $appName
 * @var array{company: string, address: string, email: string, host: string} $legal
 */
$this->layout('legal/layout', ['pageTitle' => 'Conditions d\'utilisation']);
?>
<?php $this->start('body') ?>
<h2>1. Objet</h2>
<p>Les présentes conditions régissent l'utilisation de <?= e($appName) ?>, service de backend fourni par <?= e($legal['company']) ?>. La création d'un compte vaut acceptation de ces conditions.</p>

<h2>2. Compte</h2>
<p>Vous êtes responsable de la confidentialité de vos identifiants et de vos clés API. Les clés secrètes ne doivent jamais être intégrées dans une application distribuée au public. Signalez sans délai toute utilisation non autorisée.</p>

<h2>3. Plans et limites</h2>
<p>Chaque plan comporte des limites (stockage, nombre de projets, requêtes, e-mails…). Lorsqu'une limite est atteinte, les nouvelles écritures sont refusées tandis que la lecture et la suppression restent possibles.</p>

<h2>4. Usage acceptable</h2>
<ul>
    <li>Pas de contenu illicite, de logiciel malveillant ni d'atteinte aux droits de tiers.</li>
    <li>Pas d'envoi de spam ni d'e-mails non sollicités via le service d'e-mails.</li>
    <li>Pas de tentative de contournement des mesures de sécurité ou d'accès aux données d'autres utilisateurs.</li>
</ul>
<p>Tout manquement peut entraîner la suspension du compte ou des projets concernés.</p>

<h2>5. Données</h2>
<p>Vous restez propriétaire des données de vos projets. Il vous appartient d'en conserver des sauvegardes et de respecter la réglementation applicable aux données de vos propres utilisateurs.</p>

<h2>6. Disponibilité et responsabilité</h2>
<p>Le service est fourni « en l'état ». Nous mettons en œuvre des moyens raisonnables pour assurer sa disponibilité et sa sécurité, sans garantie d'absence d'interruption.</p>

<h2>7. Résiliation</h2>
<p>Vous pouvez supprimer votre compte à tout moment depuis la page « Mon compte ». La suppression est définitive.</p>

<h2>8. Contact</h2>
<p>Pour toute question : <?= e($legal['email'] !== '' ? $legal['email'] : 'voir les mentions légales') ?>.</p>
<?php $this->stop() ?>
