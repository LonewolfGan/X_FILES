CREATE DATABASE xfiles;
USE xfiles;

CREATE TABLE filieres (
  code VARCHAR(20) PRIMARY KEY,
  name VARCHAR(100) NOT NULL
);

CREATE TABLE modules (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  code VARCHAR(20) NOT NULL,
  filiere_code VARCHAR(20),
  semester VARCHAR(10),
  FOREIGN KEY (filiere_code) REFERENCES filieres(code)
);

CREATE TABLE users (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) UNIQUE NOT NULL,
  password_hash TEXT NOT NULL,
  filiere_code VARCHAR(20),
  role ENUM('etudiant', 'admin') DEFAULT 'etudiant',
  avatar VARCHAR(255),
  avatar_data LONGBLOB,
  avatar_type VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (filiere_code) REFERENCES filieres(code)
);

CREATE TABLE documents (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL,
  file VARCHAR(255) NOT NULL,
  file_data LONGBLOB,
  file_type VARCHAR(100),
  file_size INT,
  type ENUM('cours', 'td', 'tp', 'examen', 'resume') NOT NULL,
  module_id BIGINT,
  user_id BIGINT,
  status ENUM('en_attente', 'approuve', 'rejete') DEFAULT 'en_attente',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (module_id) REFERENCES modules(id),
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE commentaires (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  content TEXT NOT NULL,
  user_id BIGINT,
  doc_id BIGINT,
  rating TINYINT CHECK (rating >= 1 AND rating <= 5),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (doc_id) REFERENCES documents(id)
);


--seed

USE xfiles;

INSERT INTO filieres (code, name) VALUES
('SDBDIA', 'Sciences des Données, Big Data & IA'),
('SITCN', 'Sécurité IT et Confiance Numérique'),
('MGSI', 'Management et Gouvernance des SI'),
('IL', 'Ingénierie Logicielle');

INSERT INTO modules (name, code, filiere_code, semester) VALUES
-- SDBDIA S1
('Mathématiques pour l\'Intelligence Artificielle', 'SDBDIA-S1-01', 'SDBDIA', 'S1'),
('Analyse numérique matricielle et Statistique', 'SDBDIA-S1-02', 'SDBDIA', 'S1'),
('Algorithmique & Programmation', 'SDBDIA-S1-03', 'SDBDIA', 'S1'),
('Réseaux informatique', 'SDBDIA-S1-04', 'SDBDIA', 'S1'),
('Architecture des ordinateurs et Systèmes d\'exploitation', 'SDBDIA-S1-05', 'SDBDIA', 'S1'),
('Langues Etrangères 1', 'SDBDIA-S1-06', 'SDBDIA', 'S1'),
('Management 1', 'SDBDIA-S1-07', 'SDBDIA', 'S1'),
-- SDBDIA S2
('Recherche Opérationnelle', 'SDBDIA-S2-01', 'SDBDIA', 'S2'),
('Compilation et Informatique quantique', 'SDBDIA-S2-02', 'SDBDIA', 'S2'),
('POO en Java', 'SDBDIA-S2-03', 'SDBDIA', 'S2'),
('Technologies Web', 'SDBDIA-S2-04', 'SDBDIA', 'S2'),
('Systèmes d\'Information et Bases de Données', 'SDBDIA-S2-05', 'SDBDIA', 'S2'),
('Langues Etrangères 2', 'SDBDIA-S2-06', 'SDBDIA', 'S2'),
('Management 2', 'SDBDIA-S2-07', 'SDBDIA', 'S2'),
-- SDBDIA S3
('Structures de données avancée', 'SDBDIA-S3-01', 'SDBDIA', 'S3'),
('Architecture Logicielle et UML', 'SDBDIA-S3-02', 'SDBDIA', 'S3'),
('Fondements du Big Data', 'SDBDIA-S3-03', 'SDBDIA', 'S3'),
('Intelligence Artificielle I: Machine Learning', 'SDBDIA-S3-04', 'SDBDIA', 'S3'),
('Bases de données NOSQL', 'SDBDIA-S3-05', 'SDBDIA', 'S3'),
('Langues Etrangères 3', 'SDBDIA-S3-06', 'SDBDIA', 'S3'),
('Transformation Digitale et Stage', 'SDBDIA-S3-07', 'SDBDIA', 'S3'),
-- SDBDIA S4
('Systèmes décisionnels', 'SDBDIA-S4-01', 'SDBDIA', 'S4'),
('Intelligence Artificielle II: Deep Learning', 'SDBDIA-S4-02', 'SDBDIA', 'S4'),
('Computer Vision et Generative IA', 'SDBDIA-S4-03', 'SDBDIA', 'S4'),
('IoT et Cloud computing', 'SDBDIA-S4-04', 'SDBDIA', 'S4'),
('Développement mobile et Metaverse', 'SDBDIA-S4-05', 'SDBDIA', 'S4'),
('Langues Etrangères 4', 'SDBDIA-S4-06', 'SDBDIA', 'S4'),
('Droit numérique et droits de propriété intellectuelle IT', 'SDBDIA-S4-07', 'SDBDIA', 'S4'),
-- SDBDIA S5
('Big Data Avancées', 'SDBDIA-S5-01', 'SDBDIA', 'S5'),
('Traitement du langage naturel (NLP)', 'SDBDIA-S5-02', 'SDBDIA', 'S5'),
('Programmation et architecture Parallèle', 'SDBDIA-S5-03', 'SDBDIA', 'S5'),
('Blockchaine et Cybersécurité', 'SDBDIA-S5-04', 'SDBDIA', 'S5'),
('Systèmes embarqués et Robotique', 'SDBDIA-S5-05', 'SDBDIA', 'S5'),
('Langues Etrangères 5', 'SDBDIA-S5-06', 'SDBDIA', 'S5'),
('Management 3 et Stage', 'SDBDIA-S5-07', 'SDBDIA', 'S5'),

-- SITCN S1
('Mathématiques appliquées', 'SITCN-S1-01', 'SITCN', 'S1'),
('Architecture des ordinateurs et Systèmes d\'exploitation', 'SITCN-S1-02', 'SITCN', 'S1'),
('Algorithmes et POO en Java', 'SITCN-S1-03', 'SITCN', 'S1'),
('Structures de données et Python', 'SITCN-S1-04', 'SITCN', 'S1'),
('Réseaux informatique', 'SITCN-S1-05', 'SITCN', 'S1'),
('Langues Etrangères 1', 'SITCN-S1-06', 'SITCN', 'S1'),
('Management 1', 'SITCN-S1-07', 'SITCN', 'S1'),
-- SITCN S2
('Recherche Opérationnelle', 'SITCN-S2-01', 'SITCN', 'S2'),
('Cryptographie appliquée et Informatique quantique', 'SITCN-S2-02', 'SITCN', 'S2'),
('Technologies WEB et Bases de données', 'SITCN-S2-03', 'SITCN', 'S2'),
('Systèmes embarqués et Cloud computing', 'SITCN-S2-04', 'SITCN', 'S2'),
('Architecture Logicielle et UML', 'SITCN-S2-05', 'SITCN', 'S2'),
('Langues Etrangères 2', 'SITCN-S2-06', 'SITCN', 'S2'),
('Management 2', 'SITCN-S2-07', 'SITCN', 'S2'),
-- SITCN S3
('Introduction à la sécurité des systèmes d\'information', 'SITCN-S3-01', 'SITCN', 'S3'),
('Sécurité des réseaux', 'SITCN-S3-02', 'SITCN', 'S3'),
('Sécurité des systèmes d\'exploitation', 'SITCN-S3-03', 'SITCN', 'S3'),
('Sécurité des bases de données & SDLC', 'SITCN-S3-04', 'SITCN', 'S3'),
('Droit des TIC et Gouvernance des SI', 'SITCN-S3-05', 'SITCN', 'S3'),
('Langues Etrangères 3', 'SITCN-S3-06', 'SITCN', 'S3'),
('Transformation Digitale et Stage', 'SITCN-S3-07', 'SITCN', 'S3'),
-- SITCN S4
('Audit de la sécurité des systèmes d\'information', 'SITCN-S4-01', 'SITCN', 'S4'),
('Data Mining et Big Data', 'SITCN-S4-02', 'SITCN', 'S4'),
('Intelligence Artificielle et applications à la cybersécurité', 'SITCN-S4-03', 'SITCN', 'S4'),
('Architecture et sécurité des systèmes complexes', 'SITCN-S4-04', 'SITCN', 'S4'),
('Ethical hacking et test d\'intrusion', 'SITCN-S4-05', 'SITCN', 'S4'),
('Langues Etrangères 4', 'SITCN-S4-06', 'SITCN', 'S4'),
('Droit du numérique et droits de propriété intellectuelle IT', 'SITCN-S4-07', 'SITCN', 'S4'),
-- SITCN S5
('Malware Analysis & Digital investigation', 'SITCN-S5-01', 'SITCN', 'S5'),
('Incident handling', 'SITCN-S5-02', 'SITCN', 'S5'),
('Sécurité des applications WEB et des applications Mobiles', 'SITCN-S5-03', 'SITCN', 'S5'),
('Sécurité de la virtualisation et du Cloud computing', 'SITCN-S5-04', 'SITCN', 'S5'),
('Gouvernance de la sécurité des SI', 'SITCN-S5-05', 'SITCN', 'S5'),
('Langues Etrangères 5', 'SITCN-S5-06', 'SITCN', 'S5'),
('Management 3 et Stage', 'SITCN-S5-07', 'SITCN', 'S5'),

-- MGSI S1
('Mathématiques Appliquées', 'MGSI-S1-01', 'MGSI', 'S1'),
('Réseaux et Administration des systèmes', 'MGSI-S1-02', 'MGSI', 'S1'),
('Algorithmique et Programmation', 'MGSI-S1-03', 'MGSI', 'S1'),
('Architecture des ordinateurs et Systèmes d\'exploitation', 'MGSI-S1-04', 'MGSI', 'S1'),
('Stratégie d\'entreprise & SI', 'MGSI-S1-05', 'MGSI', 'S1'),
('Langues Etrangères 1', 'MGSI-S1-06', 'MGSI', 'S1'),
('Management 1', 'MGSI-S1-07', 'MGSI', 'S1'),
-- MGSI S2
('Recherche Opérationnelle', 'MGSI-S2-01', 'MGSI', 'S2'),
('Systèmes d\'Information et Bases de Données Relationnelles', 'MGSI-S2-02', 'MGSI', 'S2'),
('Structures de données avancée', 'MGSI-S2-03', 'MGSI', 'S2'),
('POO en Java', 'MGSI-S2-04', 'MGSI', 'S2'),
('Technologie Web', 'MGSI-S2-05', 'MGSI', 'S2'),
('Langues Etrangères 2', 'MGSI-S2-06', 'MGSI', 'S2'),
('Management 2', 'MGSI-S2-07', 'MGSI', 'S2'),
-- MGSI S3
('Administration des Bases de données Avancées', 'MGSI-S3-01', 'MGSI', 'S3'),
('Systèmes d\'Information Distribués', 'MGSI-S3-02', 'MGSI', 'S3'),
('Audit des SI', 'MGSI-S3-03', 'MGSI', 'S3'),
('Progiciels de gestion intégrée ERP', 'MGSI-S3-04', 'MGSI', 'S3'),
('Gestion de projet et Génie logiciel', 'MGSI-S3-05', 'MGSI', 'S3'),
('Langues Etrangères 3', 'MGSI-S3-06', 'MGSI', 'S3'),
('Transformation Digitale et Stage', 'MGSI-S3-07', 'MGSI', 'S3'),
-- MGSI S4
('Systèmes décisionnels', 'MGSI-S4-01', 'MGSI', 'S4'),
('Gouvernance et Urbanisation des SI', 'MGSI-S4-02', 'MGSI', 'S4'),
('Sécurité des SI', 'MGSI-S4-03', 'MGSI', 'S4'),
('Cloud Computing et IoT', 'MGSI-S4-04', 'MGSI', 'S4'),
('Développement mobile', 'MGSI-S4-05', 'MGSI', 'S4'),
('Langues Etrangères 4', 'MGSI-S4-06', 'MGSI', 'S4'),
('Droit du numérique et droits de propriété intellectuelle IT', 'MGSI-S4-07', 'MGSI', 'S4'),
-- MGSI S5
('Méthodes Agile de conception', 'MGSI-S5-01', 'MGSI', 'S5'),
('Blockchaine et applications', 'MGSI-S5-02', 'MGSI', 'S5'),
('Intelligence Artificielle', 'MGSI-S5-03', 'MGSI', 'S5'),
('Big Data et NOSQL', 'MGSI-S5-04', 'MGSI', 'S5'),
('Ingénierie logicielle, Qualité, Test et Intégration', 'MGSI-S5-05', 'MGSI', 'S5'),
('Langues Etrangères 5', 'MGSI-S5-06', 'MGSI', 'S5'),
('Management 3 et Stage', 'MGSI-S5-07', 'MGSI', 'S5'),

-- IL S1
('Mathématiques Appliquées', 'IL-S1-01', 'IL', 'S1'),
('Algorithmes et Programmation', 'IL-S1-02', 'IL', 'S1'),
('POO en Java', 'IL-S1-03', 'IL', 'S1'),
('Réseaux informatique', 'IL-S1-04', 'IL', 'S1'),
('Architecture des ordinateurs et Systèmes d\'exploitation', 'IL-S1-05', 'IL', 'S1'),
('Langues Etrangères 1', 'IL-S1-06', 'IL', 'S1'),
('Management 1', 'IL-S1-07', 'IL', 'S1'),
-- IL S2
('Recherche Opérationnelle', 'IL-S2-01', 'IL', 'S2'),
('Administration réseaux et systèmes', 'IL-S2-02', 'IL', 'S2'),
('Structures de données avancée', 'IL-S2-03', 'IL', 'S2'),
('Technologies Web', 'IL-S2-04', 'IL', 'S2'),
('Systèmes d\'Information et Bases de Données Relationnelles', 'IL-S2-05', 'IL', 'S2'),
('Langues Etrangères 2', 'IL-S2-06', 'IL', 'S2'),
('Management 2', 'IL-S2-07', 'IL', 'S2'),
-- IL S3
('Programmation Python', 'IL-S3-01', 'IL', 'S3'),
('Compilation et Informatique quantique', 'IL-S3-02', 'IL', 'S3'),
('POO en C++ et Applications', 'IL-S3-03', 'IL', 'S3'),
('Développement WEB JEE', 'IL-S3-04', 'IL', 'S3'),
('Gestion de projet et Génie logiciel', 'IL-S3-05', 'IL', 'S3'),
('Langues Etrangères 3', 'IL-S3-06', 'IL', 'S3'),
('Transformation Digitale et Stage', 'IL-S3-07', 'IL', 'S3'),
-- IL S4
('Systèmes décisionnels', 'IL-S4-01', 'IL', 'S4'),
('Ingénierie logicielle, Qualité, Test et Intégration', 'IL-S4-02', 'IL', 'S4'),
('Intelligence Artificielle', 'IL-S4-03', 'IL', 'S4'),
('Développement mobile et Metaverse', 'IL-S4-04', 'IL', 'S4'),
('IoT et Cloud computing', 'IL-S4-05', 'IL', 'S4'),
('Langues Etrangères 4', 'IL-S4-06', 'IL', 'S4'),
('Droit du numérique et droits de propriété intellectuelle IT', 'IL-S4-07', 'IL', 'S4'),
-- IL S5
('Enterprise Resource Planning ERP', 'IL-S5-01', 'IL', 'S5'),
('Big Data et NoSQL', 'IL-S5-02', 'IL', 'S5'),
('Blockchaine et Sécurité', 'IL-S5-03', 'IL', 'S5'),
('Vision par ordinateur', 'IL-S5-04', 'IL', 'S5'),
('Tendances et évolutions IT', 'IL-S5-05', 'IL', 'S5'),
('Langues Etrangères 5', 'IL-S5-06', 'IL', 'S5'),
('Management 3 et Stage', 'IL-S5-07', 'IL', 'S5');