<?php
/**
 * Formulaire SMTP (plateforme ou projet) avec préréglages Gmail / Outlook…
 *
 * @var \App\Core\View\Template $this
 * @var string                  $action
 * @var array{host: string, port: int, encryption: string, username: string, from_email: string, from_name: string, has_password: bool}|null $settings
 * @var array<string, array{label: string, host: string, port: int, encryption: string}> $presets
 * @var array<string, mixed>    $old
 */
$v = static function (string $key, string $default = '') use ($old, $settings): string {
    if (is_scalar($old[$key] ?? null)) {
        return (string) $old[$key];
    }

    return isset($settings[$key]) && is_scalar($settings[$key]) ? (string) $settings[$key] : $default;
};
$encryption = $v('encryption', 'tls');
?>
<form method="post" action="<?= $this->url($action) ?>" class="space-y-5" novalidate data-smtp-form>
    <?= $this->csrfField() ?>
    <div>
        <p class="label">Fournisseur</p>
        <div class="flex flex-wrap gap-2">
            <?php foreach ($presets as $key => $preset): ?>
            <button type="button" class="btn-secondary min-h-9 px-3 text-xs" data-smtp-preset data-host="<?= e($preset['host']) ?>" data-port="<?= e($preset['port']) ?>" data-encryption="<?= e($preset['encryption']) ?>"><?= e($preset['label']) ?></button>
            <?php endforeach; ?>
            <span class="self-center text-xs text-muted">ou saisissez votre serveur</span>
        </div>
    </div>
    <div class="grid gap-5 sm:grid-cols-[minmax(0,1fr)_120px_160px]">
        <?= $this->insert('components/field', ['name' => 'host', 'label' => 'Serveur SMTP', 'value' => $v('host'), 'placeholder' => 'smtp.gmail.com', 'required' => true]) ?>
        <?= $this->insert('components/field', ['name' => 'port', 'label' => 'Port', 'type' => 'number', 'value' => $v('port', '587'), 'required' => true]) ?>
        <div>
            <label for="f-encryption" class="label">Chiffrement</label>
            <select id="f-encryption" name="encryption" class="input">
                <?php foreach (['tls' => 'TLS (587)', 'ssl' => 'SSL (465)', 'none' => 'Aucun'] as $key => $label): ?><option value="<?= e($key) ?>" <?php if ($encryption === $key): ?>selected<?php endif; ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="grid gap-5 sm:grid-cols-2">
        <?= $this->insert('components/field', ['name' => 'username', 'label' => 'Identifiant (adresse e-mail)', 'value' => $v('username'), 'placeholder' => 'vous@gmail.com', 'autocomplete' => 'off']) ?>
        <?= $this->insert('components/field', ['name' => 'password', 'label' => 'Mot de passe d\'application', 'type' => 'password', 'autocomplete' => 'new-password', 'placeholder' => !empty($settings['has_password']) ? '•••••••• (inchangé si vide)' : 'abcd efgh ijkl mnop', 'hint' => 'Gmail : Compte Google → Sécurité → Validation en 2 étapes → Mots de passe des applications. Stocké chiffré.']) ?>
    </div>
    <div class="grid gap-5 sm:grid-cols-2">
        <?= $this->insert('components/field', ['name' => 'from_email', 'label' => 'Adresse d\'expédition', 'type' => 'email', 'value' => $v('from_email'), 'placeholder' => 'Vide = identifiant', 'hint' => 'Avec Gmail, utilisez la même adresse que l\'identifiant.']) ?>
        <?= $this->insert('components/field', ['name' => 'from_name', 'label' => 'Nom d\'expéditeur', 'value' => $v('from_name', 'NovaBase'), 'placeholder' => 'Mon application']) ?>
    </div>
    <button type="submit" class="btn-primary">Enregistrer le serveur SMTP</button>
</form>
