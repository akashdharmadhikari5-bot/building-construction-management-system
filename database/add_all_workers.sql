-- ============================================================
-- BCMS — 20 Real-World Specialized Construction Workers
-- Full Lifecycle: Foundation -> Structural Core -> MEP -> Finishing -> Handover
-- ============================================================
USE `bcms_db`;

INSERT INTO `workers` (`name`, `phone`, `skill`, `daily_wage`, `is_active`)
SELECT tmp.* FROM (
    -- 1. Supervision & Earthwork
    SELECT 'Praveen Sharma' AS name, '9110011001' AS phone, 'Site Supervisor' AS skill, 950.00 AS daily_wage, 1 AS is_active UNION ALL
    SELECT 'Karan Singh', '9110011002', 'Excavator / JCB Operator', 850.00, 1 UNION ALL
    SELECT 'Santosh Yadav', '9110011003', 'Foundation Laborer', 450.00, 1 UNION ALL

    -- 2. RCC Structural & Masonry
    SELECT 'Mohan Tiwari', '9011223344', 'Head Mason (Rajmistri)', 750.00, 1 UNION ALL
    SELECT 'Keshav Nair', '9088990011', 'Brickwork Mason', 700.00, 1 UNION ALL
    SELECT 'Dilip Jadhav', '9077889900', 'Steel Fixer (Bar Bender)', 750.00, 1 UNION ALL
    SELECT 'Babu Lal Gurjar', '9110011004', 'Shuttering Carpenter', 720.00, 1 UNION ALL
    SELECT 'Dinesh Chauhan', '9110011005', 'Concrete Vibrator Operator', 650.00, 1 UNION ALL
    SELECT 'Ganesh Patil', '9055667788', 'General Helper / Laborer', 450.00, 1 UNION ALL
    SELECT 'Vijay Solanki', '9110011006', 'Curing Worker (Tari waala)', 400.00, 1 UNION ALL

    -- 3. MEP (Plumbing & Electrical & Waterproofing)
    SELECT 'Ajay Yadav', '9033445566', 'Plumber (Sanitary Fitter)', 750.00, 1 UNION ALL
    SELECT 'Rakesh Verma', '9044556677', 'Electrician (Wiring)', 800.00, 1 UNION ALL
    SELECT 'Mukesh Rathod', '9110011007', 'Waterproofing Specialist', 700.00, 1 UNION ALL

    -- 4. Architectural & Interior Finishing
    SELECT 'Nitin Kulkarni', '9110011008', 'Tile & Marble Mason', 750.00, 1 UNION ALL
    SELECT 'Imran Khan', '9110011009', 'MS Welder & Fabricator', 700.00, 1 UNION ALL
    SELECT 'Sunil Joshi', '9110011010', 'POP & False Ceiling Worker', 680.00, 1 UNION ALL
    SELECT 'Ramrao Bhosle', '9066778899', 'Master Painter', 650.00, 1 UNION ALL
    SELECT 'Suresh Kumar', '9022334455', 'Interior Finish Carpenter', 750.00, 1 UNION ALL
    SELECT 'Anand Shinde', '9110011011', 'Glass & Aluminum Glazier', 680.00, 1 UNION ALL
    SELECT 'Deepak Pawar', '9110011012', 'Site Safety & Cleanup', 450.00, 1
) AS tmp
WHERE NOT EXISTS (
    SELECT 1 FROM `workers` w WHERE w.name = tmp.name AND w.skill = tmp.skill
);
