-- ============================================================
-- BCMS — Demo Seed Data
-- Run AFTER schema.sql
-- ============================================================
USE `bcms_db`;

-- ============================================================
-- USERS
-- Admin password:  Admin@123
-- Client password: Client@123
-- Worker password: Worker@123
-- ============================================================
INSERT INTO `users` (`name`, `email`, `password`, `role`, `linked_id`, `is_active`) VALUES
('Admin Manager',   'admin@bcms.local',   '$2y$12$3SMuCB9G4Bn5KdaHhEo64.D2HOKHW8o55aFSeQIuJszn.19gM9uKe', 'admin',  NULL, 1),
('Ramesh Sharma',   'ramesh@bcms.local',  '$2y$12$S5ng4gFbYU99Iym3FUR3VeZdsXWlbt9qjvBwWMSGaqrosy4XqZHMG', 'client', 1,    1),
('Priya Patel',     'priya@bcms.local',   '$2y$12$S5ng4gFbYU99Iym3FUR3VeZdsXWlbt9qjvBwWMSGaqrosy4XqZHMG', 'client', 2,    1),
('Mohan Tiwari',    'mohan@bcms.local',   '$2y$12$5EP4Z5k9pxbzQ5rBMtU/PO44SrSJR/jTM71caQNfypGCVDIM3RnsC', 'worker', 1,    1),
('Suresh Kumar',    'suresh@bcms.local',  '$2y$12$5EP4Z5k9pxbzQ5rBMtU/PO44SrSJR/jTM71caQNfypGCVDIM3RnsC', 'worker', 2,    1);

-- ============================================================
-- CLIENTS
-- ============================================================
INSERT INTO `clients` (`name`, `phone`, `email`, `address`) VALUES
('Ramesh Sharma',  '9876543210', 'ramesh.sharma@email.com',  '12, MG Road, Pune, Maharashtra - 411001'),
('Priya Patel',    '9123456789', 'priya.patel@email.com',    '45, Ring Road, Nagpur, Maharashtra - 440001'),
('Vikram Singh',   '9988776655', 'vikram.singh@email.com',   '78, Station Road, Nashik, Maharashtra - 422001'),
('Anita Desai',    '9765432100', 'anita.desai@email.com',    '33, Civil Lines, Aurangabad, Maharashtra - 431001');

-- ============================================================
-- WORKERS
-- ============================================================
INSERT INTO `workers` (`name`, `phone`, `skill`, `daily_wage`, `is_active`) VALUES
('Mohan Tiwari',   '9011223344', 'Mason',            650.00, 1),
('Suresh Kumar',   '9022334455', 'Carpenter',        700.00, 1),
('Ajay Yadav',     '9033445566', 'Plumber',          600.00, 1),
('Rakesh Verma',   '9044556677', 'Electrician',      750.00, 1),
('Ganesh Patil',   '9055667788', 'Laborer',          450.00, 1),
('Ramrao Bhosle',  '9066778899', 'Painter',          500.00, 1),
('Dilip Jadhav',   '9077889900', 'Steel Fixer',      680.00, 1),
('Keshav Nair',    '9088990011', 'Mason',            620.00, 1);

-- ============================================================
-- PROJECTS
-- ============================================================
INSERT INTO `projects` (`client_id`, `project_name`, `location`, `budget`, `start_date`, `end_date`, `status`, `progress`, `description`) VALUES
(1, 'Sharma Residence',     'Kothrud, Pune',       2500000.00, '2026-01-15', '2026-12-31', 'active',    45, 'G+2 residential building for Ramesh Sharma'),
(2, 'Patel Commercial',     'Sitabuldi, Nagpur',   4800000.00, '2026-03-01', '2027-03-31', 'active',    20, 'Commercial complex with 4 shops'),
(3, 'Singh Villa',          'Gangapur Road, Nashik',1800000.00, '2025-09-01', '2026-08-31', 'completed', 100,'Single-story villa construction'),
(4, 'Desai Apartment',      'Aurangabad',          6200000.00, '2026-06-01', '2027-12-31', 'planning',   0, 'Multi-apartment residential block');

-- ============================================================
-- WORKER ASSIGNMENTS
-- ============================================================
INSERT INTO `worker_assignments` (`project_id`, `worker_id`, `assigned_date`, `is_active`) VALUES
(1, 1, '2026-01-15', 1),
(1, 2, '2026-01-15', 1),
(1, 3, '2026-02-01', 1),
(1, 5, '2026-01-15', 1),
(2, 1, '2026-03-05', 1),
(2, 4, '2026-03-05', 1),
(2, 7, '2026-03-10', 1),
(3, 6, '2025-09-01', 0),
(3, 8, '2025-09-01', 0);

-- ============================================================
-- MATERIALS
-- ============================================================
INSERT INTO `materials` (`material_name`, `unit`, `stock_quantity`, `low_stock_threshold`) VALUES
('Cement',       'bags',   0.000,  50),
('Sand',         'cubic ft',0.000, 100),
('Bricks',       'pieces', 0.000, 500),
('Steel Rods',   'kg',     0.000,  200),
('Gravel',       'cubic ft',0.000, 80),
('Paint',        'litre',  0.000,  20),
('PVC Pipe',     'metres', 0.000,  30),
('Tiles',        'sq ft',  0.000, 200),
('Glass',        'sq ft',  0.000,  50),
('Wood',         'cubic ft',0.000, 30);

-- ============================================================
-- MATERIAL PURCHASES
-- (These will trigger stock_quantity updates via app logic)
-- ============================================================
INSERT INTO `material_purchases` (`project_id`, `material_id`, `quantity`, `price`, `purchase_date`, `supplier`) VALUES
(1, 1, 200.000, 35000.00, '2026-01-20', 'ACC Cement Dealers'),
(1, 2, 500.000, 12500.00, '2026-01-22', 'Pune Sand Suppliers'),
(1, 3, 2000.000, 18000.00, '2026-01-22', 'Brick World'),
(1, 4, 500.000, 32500.00, '2026-02-01', 'Steel India'),
(2, 1, 300.000, 52500.00, '2026-03-10', 'UltraTech Dealers'),
(2, 4, 800.000, 52000.00, '2026-03-12', 'Steel India'),
(2, 7, 100.000, 8500.00,  '2026-03-15', 'Pipe World');

-- Update material stock to match purchases
UPDATE materials SET stock_quantity = (
  SELECT COALESCE(SUM(quantity), 0) FROM material_purchases WHERE material_id = materials.material_id
) - (
  SELECT COALESCE(SUM(quantity), 0) FROM material_usage WHERE material_id = materials.material_id
);

-- ============================================================
-- MATERIAL USAGE
-- ============================================================
INSERT INTO `material_usage` (`project_id`, `material_id`, `quantity`, `usage_date`, `description`) VALUES
(1, 1, 80.000,  '2026-02-10', 'Foundation work'),
(1, 2, 200.000, '2026-02-10', 'Foundation work'),
(1, 3, 800.000, '2026-02-15', 'Brick laying ground floor'),
(1, 4, 150.000, '2026-03-01', 'Roof slab reinforcement'),
(2, 1, 100.000, '2026-04-01', 'Pillar casting'),
(2, 4, 250.000, '2026-04-05', 'Column reinforcement');

-- Recalculate stock after usage
UPDATE materials SET stock_quantity = (
  SELECT COALESCE(SUM(quantity), 0) FROM material_purchases WHERE material_id = materials.material_id
) - (
  SELECT COALESCE(SUM(quantity), 0) FROM material_usage WHERE material_id = materials.material_id
);

-- ============================================================
-- ATTENDANCE
-- ============================================================
INSERT INTO `attendance` (`worker_id`, `project_id`, `attendance_date`, `status`) VALUES
(1, 1, '2026-10-27', 'present'),
(1, 1, '2026-10-28', 'present'),
(1, 1, '2026-10-29', 'present'),
(1, 1, '2026-10-30', 'absent'),
(1, 1, '2026-10-31', 'present'),
(2, 1, '2026-10-27', 'present'),
(2, 1, '2026-10-28', 'half_day'),
(2, 1, '2026-10-29', 'present'),
(2, 1, '2026-10-30', 'present'),
(2, 1, '2026-10-31', 'present'),
(3, 1, '2026-10-27', 'present'),
(3, 1, '2026-10-28', 'present'),
(3, 1, '2026-10-29', 'absent'),
(3, 1, '2026-10-30', 'present'),
(5, 1, '2026-10-27', 'present'),
(5, 1, '2026-10-28', 'present'),
(5, 1, '2026-10-29', 'present'),
(1, 2, '2026-10-27', 'present'),
(1, 2, '2026-10-28', 'present'),
(4, 2, '2026-10-27', 'present'),
(4, 2, '2026-10-28', 'present'),
(4, 2, '2026-10-29', 'present');

-- ============================================================
-- TASKS
-- ============================================================
INSERT INTO `tasks` (`project_id`, `worker_id`, `task_name`, `description`, `status`, `progress`, `due_date`) VALUES
(1, 1, 'Foundation Excavation',     'Dig foundation as per plan', 'completed', 100, '2026-02-15'),
(1, 1, 'Foundation Concrete',       'Pour concrete for foundation', 'completed', 100, '2026-03-01'),
(1, 2, 'Ground Floor Framing',      'Erect ground floor framework', 'completed', 100, '2026-04-01'),
(1, 3, 'Plumbing Ground Floor',     'Install underground plumbing', 'completed', 100, '2026-04-15'),
(1, 1, 'First Floor Brickwork',     'Build first floor walls', 'in_progress', 70, '2026-11-30'),
(1, 4, 'Electrical Wiring',         'Install electrical conduits', 'in_progress', 30, '2026-11-30'),
(1, NULL,'Roofing',                  'Roof slab construction', 'pending', 0, '2026-12-15'),
(2, 1, 'Foundation Work',           'Deep foundation for commercial', 'in_progress', 60, '2026-05-31'),
(2, 4, 'Electrical Planning',       'Plan electrical layout', 'completed', 100, '2026-04-30'),
(2, 7, 'Steel Framework',           'Erect structural steel', 'in_progress', 40, '2026-06-30');

-- ============================================================
-- EXPENSES
-- ============================================================
INSERT INTO `expenses` (`project_id`, `expense_type`, `amount`, `expense_date`, `description`) VALUES
(1, 'material', 35000.00,  '2026-01-20', 'Cement purchase - 200 bags'),
(1, 'material', 12500.00,  '2026-01-22', 'Sand purchase'),
(1, 'material', 18000.00,  '2026-01-22', 'Bricks purchase'),
(1, 'material', 32500.00,  '2026-02-01', 'Steel rods purchase'),
(1, 'labour',   45500.00,  '2026-10-31', 'Worker wages October week 4'),
(1, 'equipment',8000.00,   '2026-02-10', 'Excavation machine rental'),
(1, 'transport',3500.00,   '2026-02-12', 'Material transport charges'),
(2, 'material', 52500.00,  '2026-03-10', 'Cement purchase - 300 bags'),
(2, 'material', 52000.00,  '2026-03-12', 'Steel rods for structure'),
(2, 'material',  8500.00,  '2026-03-15', 'PVC pipes'),
(2, 'labour',   35000.00,  '2026-10-31', 'Worker wages October'),
(2, 'equipment',15000.00,  '2026-03-20', 'Crane rental'),
(3, 'material', 85000.00,  '2025-09-05', 'All materials for villa'),
(3, 'labour',   95000.00,  '2026-08-15', 'All labor charges'),
(3, 'other',    12000.00,  '2026-08-20', 'Site clearance and finishing');

-- ============================================================
-- PAYMENTS
-- ============================================================
INSERT INTO `payments` (`project_id`, `client_id`, `amount`, `payment_date`, `payment_type`, `payment_mode`, `reference_no`) VALUES
(1, 1, 500000.00, '2026-01-14', 'advance',    'bank_transfer', 'NEFT20260114001'),
(1, 1, 300000.00, '2026-04-01', 'milestone',  'cheque',        'CHQ-001-2026'),
(1, 1, 200000.00, '2026-07-15', 'milestone',  'bank_transfer', 'NEFT20260715001'),
(2, 2, 800000.00, '2026-02-28', 'advance',    'bank_transfer', 'NEFT20260228001'),
(2, 2, 500000.00, '2026-05-01', 'milestone',  'upi',           'UPI-20260501-PAT'),
(3, 3, 400000.00, '2025-08-30', 'advance',    'cash',          NULL),
(3, 3, 600000.00, '2025-12-01', 'milestone',  'cheque',        'CHQ-SIN-001'),
(3, 3, 700000.00, '2026-08-25', 'final',      'bank_transfer', 'NEFT20260825001');
