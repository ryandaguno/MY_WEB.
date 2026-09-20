USE selah_aesthetics;

-- Default admin: username=admin, password=password
INSERT INTO admins (username, email, password_hash) VALUES
('admin', 'admin@selahaesthetics.com', '$2y$12$iZm42zRUq3ZlE1iZmKV6n.TyQ9NShaFJq82/eK7e31kj028UOO1sy');

-- Services (complete list)
INSERT INTO services (name, description, price, duration_minutes, category) VALUES
-- Nails
('Manicure & Pedicure', 'Full nail care and polish treatment',            270.00,  30, 'Nails'),
('Nail Extensions',     'Acrylic or gel nail extensions',                 950.00,  40, 'Nails'),
('Foot Spa',            'Relaxing foot soak and massage',                 380.00,  15, 'Nails'),
('Eyelash Extension',   'Semi-permanent lash extensions',                 800.00,  60, 'Nails'),
-- Waxing
('Brazilian Wax',       'Full Brazilian waxing service',                  900.00,  40, 'Waxing'),
-- Facial
('Korean Facial',       'Brightening K-beauty facial treatment',          850.00,  40, 'Facial'),
('Basic Facial',        'Deep cleansing and hydrating facial',            450.00,  60, 'Facial'),
('Vampire Facial',      'PRP rejuvenating facial treatment',             2999.00,  50, 'Facial'),
('Acne Facial',         'Targeted acne clearing facial',                  850.00,  25, 'Facial'),
('Selah Signature Facial','Our exclusive premium facial',                 200.00,  45, 'Facial'),
('Facial with Diamond Peel','Diamond microdermabrasion facial',           750.00,  25, 'Facial'),
('Fractional Laser',    'Laser skin resurfacing treatment',              4000.00,  40, 'Facial'),
('Carbon Black Doll',   'Carbon laser facial for skin brightening',      1500.00,  40, 'Facial'),
('Emsculpting',         'Non-invasive body sculpting treatment',         1600.00,  40, 'Facial'),
('Cauterizon',          'Skin tag and lesion removal',                   3000.00,  20, 'Facial'),
('Diode Underarm Laser','Permanent underarm hair reduction',             3000.00,  40, 'Facial'),
-- Hair
('Hair Cut',            'Precision haircut and styling',                  300.00,  40, 'Hair'),
('Hair Color',          'Full hair color treatment',                     1500.00,  45, 'Hair'),
('Hot Oil',             'Deep conditioning hot oil treatment',            800.00,  30, 'Hair'),
('Hair Rebond',         'Japanese hair rebonding treatment',             1500.00,  40, 'Hair'),
('Hair Balayage',       'Hand-painted balayage highlights',              3000.00,  40, 'Hair'),
('Keratin Treatment',   'Smoothing keratin treatment',                   1500.00,  40, 'Hair'),
('Hair Cellophane',     'Glossy hair cellophane color treatment',         900.00,  20, 'Hair'),
('Hair Perm',           'Permanent wave styling treatment',              2000.00,  40, 'Hair'),
('Brazilian Blowout',   'Smoothing Brazilian blowout treatment',         1500.00,  40, 'Hair');

-- Stylists
INSERT INTO stylists (name, specialty, bio) VALUES
('Emma',      'Hair',   'Hair colouring specialist with 5 years experience.'),
('Charlotte', 'Facial', 'Certified skin therapist specialising in Korean facials.'),
('Vivian',    'Nails',  'Nail art and extension expert.');

-- Schedules (time slots)
INSERT INTO schedules (stylist_id, slot_date, start_time, end_time) VALUES
(1, CURDATE() + INTERVAL 1 DAY, '09:00:00', '09:40:00'),
(1, CURDATE() + INTERVAL 1 DAY, '10:00:00', '10:40:00'),
(1, CURDATE() + INTERVAL 1 DAY, '11:00:00', '11:40:00'),
(2, CURDATE() + INTERVAL 1 DAY, '09:00:00', '09:40:00'),
(2, CURDATE() + INTERVAL 1 DAY, '10:00:00', '10:40:00'),
(3, CURDATE() + INTERVAL 2 DAY, '13:00:00', '13:30:00'),
(3, CURDATE() + INTERVAL 2 DAY, '14:00:00', '14:30:00'),
(1, CURDATE() + INTERVAL 2 DAY, '09:00:00', '09:40:00'),
(2, CURDATE() + INTERVAL 3 DAY, '10:00:00', '10:40:00'),
(2, CURDATE() + INTERVAL 3 DAY, '11:00:00', '11:40:00');

-- Sample clients (password = password)
INSERT INTO clients (username, email, password_hash, phone, is_verified) VALUES
('joshua', 'joshua@example.com', '$2y$12$iZm42zRUq3ZlE1iZmKV6n.TyQ9NShaFJq82/eK7e31kj028UOO1sy', '09171234567', 1),
('ryan',   'ryan@example.com',   '$2y$12$iZm42zRUq3ZlE1iZmKV6n.TyQ9NShaFJq82/eK7e31kj028UOO1sy', '09187654321', 1);
