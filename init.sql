-- XFILES - Database Initialization for Render
-- Execute this script after database creation

-- Insert filieres
INSERT IGNORE INTO filieres (code, name) VALUES
('SDBDIA', 'Sciences des Données, Big Data & IA'),
('SITCN', 'Sécurité IT et Confiance Numérique'),
('MGSI', 'Management et Gouvernance des SI'),
('IL', 'Ingénierie Logicielle');

-- Insert admin user
-- Login: ENSIASD
-- Password: ENSIASD2026
-- Note: Password is hashed with bcrypt
INSERT IGNORE INTO users (name, email, password_hash, role) VALUES
('Administrateur ENSIASD', 'ensiasd@gmail.com', '$2y$10$6uerKqEteSRoQPBpYAzZcuEh17HxTHiSo9mdE8tnUDsou6nz32vtO', 'admin');
-- The hash above is for 'ENSIASD2026' - generated with password_hash()

-- Insert sample modules (first semester of each filiere)
INSERT IGNORE INTO modules (name, code, filiere_code, semester) VALUES
('Mathématiques pour l\'Intelligence Artificielle', 'SDBDIA-S1-01', 'SDBDIA', 'S1'),
('Algorithmique & Programmation', 'SDBDIA-S1-03', 'SDBDIA', 'S1'),
('Mathématiques appliquées', 'SITCN-S1-01', 'SITCN', 'S1'),
('Algorithmes et POO en Java', 'SITCN-S1-03', 'SITCN', 'S1'),
('Mathématiques Appliquées', 'MGSI-S1-01', 'MGSI', 'S1'),
('Algorithmique et Programmation', 'MGSI-S1-03', 'MGSI', 'S1'),
('Mathématiques Appliquées', 'IL-S1-01', 'IL', 'S1'),
('Algorithmes et Programmation', 'IL-S1-02', 'IL', 'S1');
