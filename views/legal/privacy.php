<?php
/**
 * @var \App\Core\View\Template                                              $this
 * @var string                                                               $appName
 * @var array{company: string, address: string, email: string, host: string} $legal
 */
$this->layout('legal/layout', ['pageTitle' => 'Politique de confidentialité']);
?>
<?php $this->start('body') ?>
<p><?= e($legal['company']) ?> (« nous ») s'engage à protéger les données personnelles des utilisateurs de <?= e($appName) ?>, conformément au Règlement général sur la protection des données (RGPD).</p>

<h2>Données collectées</h2>
<ul>
    <li><strong>Compte</strong> : nom, adresse e-mail, mot de passe (stocké uniquement sous forme de hash Argon2id).</li>
    <li><strong>Sécurité</strong> : adresse IP, navigateur et horodatage des connexions, journal d'audit des actions sensibles.</li>
    <li><strong>Contenu de vos projets</strong> : données, fichiers et e-mails que vous traitez via le service, pour votre compte.</li>
</ul>

<h2>Finalités</h2>
<p>Fournir et sécuriser le service (authentification, prévention des abus, double authentification), vous contacter pour des raisons liées à votre compte (vérification d'adresse, réinitialisation de mot de passe, alertes de sécurité ou de quota).</p>

<h2>Durées de conservation</h2>
<p>Les données du compte sont conservées tant que celui-ci est actif et supprimées lors de sa suppression. Les journaux techniques sont conservés au plus quelques semaines ; le journal d'audit de sécurité peut être conservé plus longtemps pour répondre aux obligations légales.</p>

<h2>Sécurité</h2>
<p>Connexions chiffrées (HTTPS), secrets chiffrés au repos, isolation de chaque projet dans sa propre base de données, double authentification disponible pour tous les comptes.</p>

<h2>Cookies</h2>
<p>Nous utilisons uniquement un cookie de session strictement nécessaire au fonctionnement du service. Aucun cookie publicitaire ni traceur tiers. Votre choix de thème (clair/sombre) est conservé localement dans votre navigateur.</p>

<h2>Vos droits</h2>
<p>Vous disposez d'un droit d'accès, de rectification, d'effacement, de limitation, d'opposition et de portabilité. Vous pouvez supprimer votre compte à tout moment depuis la page « Mon compte », ou nous écrire à <?= e($legal['email'] !== '' ? $legal['email'] : 'l\'adresse indiquée dans les mentions légales') ?>. Vous pouvez également saisir l'autorité de contrôle compétente (en France, la CNIL).</p>
<?php $this->stop() ?>
