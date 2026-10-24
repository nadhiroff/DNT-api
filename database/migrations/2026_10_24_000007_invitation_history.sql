-- L'historique des invitations est conservé quand le compte en attente est supprimé (révocation).
ALTER TABLE invitations DROP FOREIGN KEY fk_invitations_user;
ALTER TABLE invitations ADD CONSTRAINT fk_invitations_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL;
