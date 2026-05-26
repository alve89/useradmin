CREATE TABLE IF NOT EXISTS sso_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uid VARCHAR(190) NOT NULL UNIQUE,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    given_name VARCHAR(190) NOT NULL,
    family_name VARCHAR(190) NOT NULL,
    display_name VARCHAR(190) NOT NULL,
    mail VARCHAR(190) NOT NULL,
    imap_user VARCHAR(190) NOT NULL,
    quota VARCHAR(50) NOT NULL DEFAULT '512 MB',
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sso_groups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL UNIQUE,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sso_user_groups (
    user_id INT UNSIGNED NOT NULL,
    group_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, group_id),
    CONSTRAINT fk_sso_user_groups_user
        FOREIGN KEY (user_id) REFERENCES sso_users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_sso_user_groups_group
        FOREIGN KEY (group_id) REFERENCES sso_groups(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO sso_groups (name, description) VALUES
('InnerCircle', 'Interner Kreis'),
('Administrativteam', 'Administrative Aufgaben'),
('Operativteam', 'Operative Aufgaben'),
('PuM', 'PuM'),
('admin', 'Administratoren');

INSERT IGNORE INTO sso_users
(uid, enabled, given_name, family_name, display_name, mail, imap_user, quota, notes)
VALUES
('brian.croseck', 1, 'Brian', 'Croseck', 'Brian Croseck', 'brian.croseck@die-kerwe.de', 'brian.croseck@die-kerwe.de', '512 MB', NULL),
('daniel.schmitt', 1, 'Daniel', 'Schmitt', 'Daniel Schmitt', 'daniel.schmitt@die-kerwe.de', 'daniel.schmitt@die-kerwe.de', '512 MB', NULL),
('dennis.morweiser', 1, 'Dennis', 'Morweiser', 'Dennis Morweiser', 'dennis.morweiser@die-kerwe.de', 'dennis.morweiser@die-kerwe.de', '512 MB', NULL),
('dominik.ewald', 1, 'Dominik', 'Ewald', 'Dominik Ewald', 'dominik.ewald@die-kerwe.de', 'dominik.ewald@die-kerwe.de', '512 MB', NULL),
('jan.gruber', 1, 'Jan', 'Gruber', 'Jan Gruber', 'jan.gruber@die-kerwe.de', 'jan.gruber@die-kerwe.de', '512 MB', NULL),
('jan.sauer', 1, 'Jan', 'Sauer', 'Jan Sauer', 'jan.sauer@die-kerwe.de', 'jan.sauer@die-kerwe.de', '512 MB', NULL),
('marlon.rossow', 1, 'Marlon', 'Rossow', 'Marlon Rossow', 'marlon.rossow@die-kerwe.de', 'marlon.rossow@die-kerwe.de', '512 MB', NULL),
('sebastian.sauer', 1, 'Sebastian', 'Sauer', 'Sebastian Sauer', 'sebastian.sauer@die-kerwe.de', 'sebastian.sauer@die-kerwe.de', '512 MB', NULL),
('simon.klinger', 1, 'Simon', 'Klinger', 'Simon Klinger', 'simon.klinger@die-kerwe.de', 'simon.klinger@die-kerwe.de', '512 MB', NULL),
('alexander.sauer', 1, 'Alexander', 'Sauer', 'Alexander Sauer', 'alexander.sauer@die-kerwe.de', 'alexander.sauer@die-kerwe.de', '512 MB', NULL),
('stefan.herzog', 1, 'Stefan', 'Herzog', 'Stefan Herzog', 'stefan.herzog@die-kerwe.de', 'stefan.herzog@die-kerwe.de', '512 MB', NULL),
('tobias.sauer', 1, 'Tobias', 'Sauer', 'Tobias Sauer', 'tobias.sauer@die-kerwe.de', 'tobias.sauer@die-kerwe.de', '512 MB', NULL);

INSERT IGNORE INTO sso_user_groups (user_id, group_id)
SELECT u.id, g.id
FROM sso_users u
JOIN sso_groups g ON g.name = 'InnerCircle';
