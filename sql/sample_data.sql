-- =====================================================
-- Sample Data for Travagir Travel Agency Database
-- =====================================================
-- This file contains sample data for all 17 tables
-- Order of insertion respects foreign key constraints
-- =====================================================
-- Drop if exists
DROP VIEW IF EXISTS public.unified_events;

-- Recreate with schema-qualified table names
CREATE VIEW public.unified_events AS
SELECT 
    id, user_id, 'voyage_visits' AS type, 
    visit_time AS created_at, 
    jsonb_build_object('voyage_id', voyage_id, 'source', source, 'duration', view_duration_seconds) AS data
FROM public.voyage_visits

UNION ALL

SELECT 
    id, user_id, 'search' AS type, 
    search_time AS created_at,
    jsonb_build_object('query', search_query, 'type', search_type, 'results', results_found) AS data
FROM public.search_history

UNION ALL

SELECT 
    id, user_id, 'login' AS type, 
    login_time AS created_at,
    jsonb_build_object('method', login_method, 'ip', ip_address) AS data
FROM public.user_logins;
-- =====================================================
-- 1. ASSOCIATIONS (no dependencies)
-- =====================================================
INSERT INTO associations (name, company_code, discount_rate) VALUES
('Frequent Travelers Club', 'FTC2024', 10.00),
('Corporate Partners', 'CP2024', 15.00),
('Student Association', 'SA2024', 20.00),
('Senior Citizens Club', 'SCC2024', 12.50),
('Travel Enthusiasts', 'TE2024', 8.00);

-- =====================================================
-- 2. USERS (no dependencies)
-- =====================================================
INSERT INTO users (username, email, password, tel, image_url, created_at) VALUES
('john_traveler', 'john.doe@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+212612345678', 'https://res.cloudinary.com/travagir/image/upload/v1/users/john_doe', '2024-01-15 10:30:00'),
('sarah_explorer', 'sarah.martin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+212698765432', 'https://res.cloudinary.com/travagir/image/upload/v1/users/sarah_martin', '2024-02-20 14:45:00'),
('mike_adventurer', 'mike.wilson@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+212654321098', 'https://res.cloudinary.com/travagir/image/upload/v1/users/mike_wilson', '2024-03-10 09:15:00'),
('emma_nature', 'emma.johnson@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+212612345679', 'https://res.cloudinary.com/travagir/image/upload/v1/users/emma_johnson', '2024-04-05 16:20:00'),
('alex_beach', 'alex.brown@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+212698765433', 'https://res.cloudinary.com/travagir/image/upload/v1/users/alex_brown', '2024-05-12 11:00:00'),
('admin_user', 'admin@travagir.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+212600000000', 'https://res.cloudinary.com/travagir/image/upload/v1/users/admin', '2024-01-01 08:00:00'),
('lisa_mountain', 'lisa.davis@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+212654321099', 'https://res.cloudinary.com/travagir/image/upload/v1/users/lisa_davis', '2024-06-18 13:30:00'),
('david_culture', 'david.miller@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+212612345680', 'https://res.cloudinary.com/travagir/image/upload/v1/users/david_miller', '2024-07-22 10:45:00');

-- =====================================================
-- 3. ADMINS (depends on users)
-- =====================================================
INSERT INTO admins (user_id, access_level) VALUES
(6, 3);  -- admin_user has full access

-- =====================================================
-- 4. VOYAGES (no dependencies)
-- =====================================================
INSERT INTO voyages (title, description, destination, start_date, end_date, price, image_url, created_at) VALUES
('Marrakech Desert Adventure', 'Experience the magic of the Sahara Desert with camel trekking, traditional Berber camps, and stunning sunset views over the dunes.', 'Marrakech, Morocco', '2024-09-15', '2024-09-20', 450.00, 'https://res.cloudinary.com/travagir/image/upload/v1/voyages/marrakech_desert', '2024-01-20 10:00:00'),
('Marrakech Cultural Tour', 'Explore the rich history and vibrant culture of Marrakech, visiting ancient palaces, bustling souks, and beautiful gardens.', 'Marrakech, Morocco', '2024-10-01', '2024-10-05', 350.00, 'https://res.cloudinary.com/travagir/image/upload/v1/voyages/marrakech_cultural', '2024-02-10 11:30:00'),
('Atlas Mountains Trekking', 'Challenge yourself with a thrilling trek through the Atlas Mountains, visiting traditional villages and breathtaking peaks.', 'Atlas Mountains, Morocco', '2024-11-10', '2024-11-15', 550.00, 'https://res.cloudinary.com/travagir/image/upload/v1/voyages/atlas_trekking', '2024-03-05 09:45:00'),
('Essaouira Coastal Escape', 'Relax in the charming coastal town of Essaouira, known for its windsurfing, fresh seafood, and laid-back atmosphere.', 'Essaouira, Morocco', '2024-12-01', '2024-12-05', 380.00, 'https://res.cloudinary.com/travagir/image/upload/v1/voyages/essaouira_coastal', '2024-04-15 14:20:00'),
('Fes Historical Journey', 'Step back in time in Fes, exploring the worlds oldest university, ancient medina, and traditional artisan workshops.', 'Fes, Morocco', '2025-01-15', '2025-01-20', 480.00, 'https://res.cloudinary.com/travagir/image/upload/v1/voyages/fes_historical', '2024-05-20 10:15:00'),
('Agadir Beach Paradise', 'Enjoy a luxurious beach vacation in Agadir with all-inclusive resorts, water sports, and vibrant nightlife.', 'Agadir, Morocco', '2025-02-01', '2025-02-07', 620.00, 'https://res.cloudinary.com/travagir/image/upload/v1/voyages/agadir_beach', '2024-06-10 16:00:00'),
('Chefchaouen Blue City', 'Discover the stunning blue-washed streets of Chefchaouen, a photographer''s paradise in the Rif Mountains.', 'Chefchaouen, Morocco', '2025-03-10', '2025-03-14', 420.00, 'https://res.cloudinary.com/travagir/image/upload/v1/voyages/chefchaouen_blue', '2024-07-05 12:30:00'),
('Ouarzazate Cinema Tour', 'Visit the Hollywood of Morocco, explore famous film studios and ancient kasbahs used in many blockbuster movies.', 'Ouarzazate, Morocco', '2025-04-05', '2025-04-09', 390.00, 'https://res.cloudinary.com/travagir/image/upload/v1/voyages/ouarzazate_cinema', '2024-08-12 15:45:00');

-- =====================================================
-- 5. ACTIVITIES (depends on voyages)
-- =====================================================
INSERT INTO activities (voyage_id, name, description, duration_hours, price_per_person, location, created_at, updated_at) VALUES
(1, 'Camel Trekking', 'Ride camels across the Sahara dunes at sunset', 4, 45.00, 'Sahara Desert', '2024-01-20 11:00:00', '2024-01-20 11:00:00'),
(1, 'Berber Camp Stay', 'Overnight in traditional Berber tent with dinner and music', 24, 80.00, 'Sahara Desert Camp', '2024-01-20 11:05:00', '2024-01-20 11:05:00'),
(1, 'Sandboarding', 'Experience the thrill of sandboarding on the dunes', 2, 30.00, 'Sahara Desert', '2024-01-20 11:10:00', '2024-01-20 11:10:00'),
(2, 'Medina Walking Tour', 'Guided tour through the ancient medina', 3, 25.00, 'Marrakech Medina', '2024-02-10 12:00:00', '2024-02-10 12:00:00'),
(2, 'Bahia Palace Visit', 'Explore the stunning 19th-century palace', 2, 15.00, 'Bahia Palace', '2024-02-10 12:05:00', '2024-02-10 12:05:00'),
(2, 'Jemaa el-Fnaa Experience', 'Evening visit to the famous square with henna art', 3, 20.00, 'Jemaa el-Fnaa', '2024-02-10 12:10:00', '2024-02-10 12:10:00'),
(3, 'Mountain Trekking', 'Trek through Atlas Mountain trails', 6, 60.00, 'Atlas Mountains', '2024-03-05 10:00:00', '2024-03-05 10:00:00'),
(3, 'Berber Village Visit', 'Traditional lunch in a Berber village', 4, 35.00, 'Imlil Village', '2024-03-05 10:05:00', '2024-03-05 10:05:00'),
(3, 'Toubkal Summit Attempt', 'Challenge Mount Toubkal, highest peak in North Africa', 8, 90.00, 'Mount Toubkal', '2024-03-05 10:10:00', '2024-03-05 10:10:00'),
(4, 'Windsurfing Lesson', 'Learn windsurfing in Essaouira', 2, 40.00, 'Essaouira Beach', '2024-04-15 15:00:00', '2024-04-15 15:00:00'),
(4, 'Fresh Seafood Dinner', 'Dinner at a beachfront restaurant', 2, 50.00, 'Essaouira Port', '2024-04-15 15:05:00', '2024-04-15 15:05:00'),
(4, 'Skala Walk', 'Stroll along the historic sea wall', 1, 0.00, 'Skala du Port', '2024-04-15 15:10:00', '2024-04-15 15:10:00'),
(5, 'Medina Heritage Tour', 'Explore the UNESCO World Heritage medina', 4, 30.00, 'Fes Medina', '2024-05-20 11:00:00', '2024-05-20 11:00:00'),
(5, 'Tanneries Visit', 'Visit the famous Chouara Tanneries', 2, 20.00, 'Chouara Tanneries', '2024-05-20 11:05:00', '2024-05-20 11:05:00'),
(5, 'Artisan Workshop', 'Traditional pottery and mosaic workshop', 3, 45.00, 'Fes Artisan Quarter', '2024-05-20 11:10:00', '2024-05-20 11:10:00'),
(6, 'Surfing Lesson', 'Learn to surf in Agadir', 2, 35.00, 'Agadir Beach', '2024-06-10 17:00:00', '2024-06-10 17:00:00'),
(6, 'Spa Day', 'Full day at luxury spa', 4, 100.00, 'Agadir Spa Resort', '2024-06-10 17:05:00', '2024-06-10 17:05:00'),
(7, 'Photography Walk', 'Capture the blue streets of Chefchaouen', 3, 0.00, 'Chefchaouen Old Town', '2024-07-05 13:00:00', '2024-07-05 13:00:00'),
(7, 'Rif Mountains Hike', 'Day hike in the surrounding mountains', 5, 25.00, 'Rif Mountains', '2024-07-05 13:05:00', '2024-07-05 13:05:00'),
(8, 'Film Studios Tour', 'Visit Atlas Studios and Cinema Museum', 3, 25.00, 'Atlas Studios', '2024-08-12 16:00:00', '2024-08-12 16:00:00'),
(8, 'Kasbah Visit', 'Explore the ancient Ait Benhaddou kasbah', 4, 20.00, 'Ait Benhaddou', '2024-08-12 16:05:00', '2024-08-12 16:05:00');

-- =====================================================
-- 6. OFFERS (depends on voyages)
-- =====================================================
INSERT INTO offers (voyage_id, title, description, discount_percentage, start_date, end_date, is_active, created_at, updated_at) VALUES
(1, 'Early Bird Desert Special', 'Book your desert adventure 30 days in advance and save 15%!', 15.00, '2024-08-01', '2024-09-14', TRUE, '2024-07-01 10:00:00', '2024-07-01 10:00:00'),
(2, 'Cultural Heritage Discount', 'Explore Marrakechs heritage at 10% off', 10.00, '2024-09-01', '2024-10-31', TRUE, '2024-08-15 11:00:00', '2024-08-15 11:00:00'),
(3, 'Mountain Explorer Deal', 'Adventure seekers save 20% on Atlas trekking', 20.00, '2024-10-01', '2024-11-09', TRUE, '2024-09-10 09:00:00', '2024-09-10 09:00:00'),
(4, 'Coastal Escape Special', 'Relax in Essaouira with 12% discount', 12.00, '2024-11-01', '2024-12-31', TRUE, '2024-10-20 14:00:00', '2024-10-20 14:00:00'),
(5, 'New Year Heritage Offer', 'Start 2025 with a journey through Fes history', 18.00, '2024-12-15', '2025-01-14', TRUE, '2024-12-01 10:00:00', '2024-12-01 10:00:00'),
(6, 'Winter Beach Getaway', 'Escape to Agadir this winter with 25% off', 25.00, '2025-01-01', '2025-02-28', TRUE, '2024-12-10 15:00:00', '2024-12-10 15:00:00'),
(7, 'Spring Blue City Special', 'Visit Chefchaouen in spring with 10% discount', 10.00, '2025-02-15', '2025-03-31', TRUE, '2025-01-20 12:00:00', '2025-01-20 12:00:00'),
(8, 'Cinema Lovers Package', 'Visit the Hollywood of Morocco at 15% off', 15.00, '2025-03-01', '2025-04-30', TRUE, '2025-02-15 10:00:00', '2025-02-15 10:00:00'),
(1, 'Last Minute Desert Deal', 'Spontaneous travelers save 20%!', 20.00, '2024-09-10', '2024-09-14', TRUE, '2024-09-05 08:00:00', '2024-09-05 08:00:00'),
(2, 'Couple''s Cultural Retreat', 'Romantic getaway for two at 15% off', 15.00, '2024-09-15', '2024-10-15', TRUE, '2024-09-01 09:00:00', '2024-09-01 09:00:00');

-- =====================================================
-- 7. RESERVATIONS (depends on users, voyages, offers)
-- =====================================================
INSERT INTO reservations (user_id, voyage_id, offer_id, reservation_date, number_of_people, total_price, status, special_requests, payment_status, payment_date, updated_at) VALUES
(1, 1, 1, '2024-08-15 14:30:00', 2, 765.00, 'CONFIRMED', 'Vegetarian meals preferred', 'PAID', '2024-08-15 14:35:00', '2024-08-15 14:35:00'),
(2, 2, 2, '2024-09-20 10:00:00', 2, 630.00, 'CONFIRMED', 'Early morning pickup', 'PAID', '2024-09-20 10:05:00', '2024-09-20 10:05:00'),
(3, 3, 3, '2024-10-25 16:45:00', 1, 440.00, 'CONFIRMED', 'Experienced hiker, need challenging route', 'PAID', '2024-10-25 16:50:00', '2024-10-25 16:50:00'),
(4, 4, 4, '2024-11-15 09:20:00', 2, 668.80, 'PENDING', 'Ocean view room if possible', 'PENDING', NULL, '2024-11-15 09:20:00'),
(5, 6, 6, '2025-01-10 11:00:00', 2, 930.00, 'CONFIRMED', 'Anniversary trip - special arrangements', 'PAID', '2025-01-10 11:10:00', '2025-01-10 11:10:00'),
(7, 5, 5, '2024-12-20 13:30:00', 3, 1180.80, 'CONFIRMED', 'One vegetarian in group', 'PAID', '2024-12-20 13:40:00', '2024-12-20 13:40:00'),
(8, 7, 7, '2025-02-25 15:15:00', 2, 756.00, 'PENDING', 'Photography-focused itinerary', 'PENDING', NULL, '2025-02-25 15:15:00'),
(1, 8, 8, '2025-03-20 10:30:00', 1, 331.50, 'CONFIRMED', 'Film enthusiast', 'PAID', '2025-03-20 10:35:00', '2025-03-20 10:35:00'),
(2, 1, 9, '2024-09-12 12:00:00', 2, 720.00, 'CANCELLED', 'Spontaneous trip - was cancelled by user', 'REFUNDED', '2024-09-12 18:00:00', '2024-09-13 09:00:00'),
(3, 2, 10, '2024-09-25 14:00:00', 2, 595.00, 'COMPLETED', 'Honeymoon trip', 'PAID', '2024-09-25 14:10:00', '2024-10-06 18:00:00'),
(4, 3, NULL, '2024-10-30 09:00:00', 1, 550.00, 'COMPLETED', 'First time trekking', 'PAID', '2024-10-30 09:05:00', '2024-11-16 20:00:00'),
(5, 4, NULL, '2024-11-25 16:30:00', 2, 760.00, 'COMPLETED', 'Surfers - need surf equipment', 'PAID', '2024-11-25 16:35:00', '2024-12-06 19:00:00');

-- =====================================================
-- 8. RECLAMATIONS (depends on reservations, users)
-- =====================================================
INSERT INTO reclamations (reservation_id, user_id, title, description, reclamation_date, status, priority, admin_response, response_date, resolution_date, created_at, updated_at) VALUES
(4, 4, 'Room Quality Not as Expected', 'The room provided was not ocean view as requested. Very disappointed with the accommodation.', '2024-12-02 10:00:00', 'RESOLVED', 'MEDIUM', 'We apologize for the inconvenience. We have applied a 15% discount to your next booking as compensation.', '2024-12-03 14:00:00', '2024-12-03 14:00:00', '2024-12-02 10:00:00', '2024-12-03 14:00:00'),
(9, 2, 'Trip Cancellation Request', 'Need to cancel due to unexpected work commitment. Please process refund.', '2024-09-13 08:00:00', 'CLOSED', 'HIGH', 'Your reservation has been cancelled and full refund has been processed to your original payment method.', '2024-09-13 10:00:00', '2024-09-13 10:00:00', '2024-09-13 08:00:00', '2024-09-13 10:00:00'),
(1, 1, 'Activity Scheduling Conflict', 'The camel trekking was scheduled at the same time as the Berber camp dinner. Please reschedule.', '2024-09-16 15:00:00', 'RESOLVED', 'LOW', 'We have rescheduled your camel trekking to the next morning at 6 AM. Enjoy the sunrise over the dunes!', '2024-09-16 17:00:00', '2024-09-16 17:00:00', '2024-09-16 15:00:00', '2024-09-16 17:00:00');

-- =====================================================
-- 9. USER DOCUMENTS (depends on users)
-- =====================================================
INSERT INTO user_documents (user_id, first_name, last_name, date_of_birth, nationality, passport_number, passport_expiry_date, cin_number, cin_creation_date, created_at, updated_at) VALUES
(1, 'John', 'Doe', '1990-05-15', 'American', 'P12345678', '2030-05-15', 'CIN123456', '2018-01-10', '2024-01-15 10:30:00', '2024-01-15 10:30:00'),
(2, 'Sarah', 'Martin', '1988-08-22', 'British', 'P87654321', '2029-08-22', 'CIN654321', '2017-06-15', '2024-02-20 14:45:00', '2024-02-20 14:45:00'),
(3, 'Mike', 'Wilson', '1992-03-10', 'Canadian', 'P11223344', '2028-03-10', 'CIN112233', '2019-02-20', '2024-03-10 09:15:00', '2024-03-10 09:15:00'),
(4, 'Emma', 'Johnson', '1985-11-30', 'French', 'P55667788', '2027-11-30', 'CIN556677', '2016-09-05', '2024-04-05 16:20:00', '2024-04-05 16:20:00'),
(5, 'Alex', 'Brown', '1995-07-18', 'German', 'P99887766', '2031-07-18', 'CIN998877', '2020-03-12', '2024-05-12 11:00:00', '2024-05-12 11:00:00'),
(7, 'Lisa', 'Davis', '1991-02-14', 'Spanish', 'P44556677', '2029-02-14', 'CIN445566', '2018-08-22', '2024-06-18 13:30:00', '2024-06-18 13:30:00'),
(8, 'David', 'Miller', '1987-09-25', 'Italian', 'P33221100', '2028-09-25', 'CIN332211', '2017-11-30', '2024-07-22 10:45:00', '2024-07-22 10:45:00');

-- =====================================================
-- 10. REFUND REQUESTS (depends on reclamations, users, reservations)
-- =====================================================
INSERT INTO refund_requests (reclamation_id, requester_id, reservation_id, amount, reason, status, created_at) VALUES
(2, 2, NULL, 720.00, 'Trip cancelled due to unexpected work commitment - force majeure', 'APPROVED', '2024-09-13 08:30:00');

-- =====================================================
-- 11. USER OFFERS (depends on users, offers)
-- =====================================================
INSERT INTO user_offers (user_id, offer_id, claimed_at, status) VALUES
(1, 1, '2024-08-10 10:00:00', 'USED'),
(2, 2, '2024-09-15 14:00:00', 'USED'),
(3, 3, '2024-10-20 09:00:00', 'USED'),
(4, 4, '2024-11-10 16:00:00', 'ACTIVE'),
(5, 6, '2025-01-05 11:00:00', 'USED'),
(7, 5, '2024-12-15 13:00:00', 'USED'),
(8, 7, '2025-02-20 15:00:00', 'ACTIVE'),
(1, 8, '2025-03-15 10:00:00', 'ACTIVE'),
(2, 9, '2024-09-11 12:00:00', 'EXPIRED'),
(3, 10, '2024-09-22 14:00:00', 'USED');

-- =====================================================
-- 12. USER ASSOCIATIONS (depends on users, associations)
-- =====================================================
INSERT INTO user_associations (user_id, association_id) VALUES
(1, 1),
(2, 2),
(3, 1),
(4, 4),
(5, 3),
(7, 1),
(8, 5);

-- =====================================================
-- 13. VOYAGE IMAGES (depends on voyages)
-- =====================================================
INSERT INTO voyage_images (voyage_id, image_url, cloudinary_public_id, created_at, updated_at) VALUES
(1, 'https://res.cloudinary.com/travagir/image/upload/v1/voyages/marrakech_desert_1', 'voyages/marrakech_desert_1', '2024-01-20 10:05:00', '2024-01-20 10:05:00'),
(1, 'https://res.cloudinary.com/travagir/image/upload/v1/voyages/marrakech_desert_2', 'voyages/marrakech_desert_2', '2024-01-20 10:06:00', '2024-01-20 10:06:00'),
(1, 'https://res.cloudinary.com/travagir/image/upload/v1/voyages/marrakech_desert_3', 'voyages/marrakech_desert_3', '2024-01-20 10:07:00', '2024-01-20 10:07:00'),
(2, 'https://res.cloudinary.com/travagir/image/upload/v1/voyages/marrakech_cultural_1', 'voyages/marrakech_cultural_1', '2024-02-10 11:35:00', '2024-02-10 11:35:00'),
(2, 'https://res.cloudinary.com/travagir/image/upload/v1/voyages/marrakech_cultural_2', 'voyages/marrakech_cultural_2', '2024-02-10 11:36:00', '2024-02-10 11:36:00'),
(3, 'https://res.cloudinary.com/travagir/image/upload/v1/voyages/atlas_trekking_1', 'voyages/atlas_trekking_1', '2024-03-05 09:50:00', '2024-03-05 09:50:00'),
(3, 'https://res.cloudinary.com/travagir/image/upload/v1/voyages/atlas_trekking_2', 'voyages/atlas_trekking_2', '2024-03-05 09:51:00', '2024-03-05 09:51:00'),
(3, 'https://res.cloudinary.com/travagir/image/upload/v1/voyages/atlas_trekking_3', 'voyages/atlas_trekking_3', '2024-03-05 09:52:00', '2024-03-05 09:52:00'),
(4, 'https://res.cloudinary.com/travagir/image/upload/v1/voyages/essaouira_coastal_1', 'voyages/essaouira_coastal_1', '2024-04-15 14:25:00', '2024-04-15 14:25:00'),
(4, 'https://res.cloudinary.com/travagir/image/upload/v1/voyages/essaouira_coastal_2', 'voyages/essaouira_coastal_2', '2024-04-15 14:26:00', '2024-04-15 14:26:00'),
(5, 'https://res.cloudinary.com/travagir/image/upload/v1/voyages/fes_historical_1', 'voyages/fes_historical_1', '2024-05-20 10:20:00', '2024-05-20 10:20:00'),
(5, 'https://res.cloudinary.com/travagir/image/upload/v1/voyages/fes_historical_2', 'voyages/fes_historical_2', '2024-05-20 10:21:00', '2024-05-20 10:21:00'),
(6, 'https://res.cloudinary.com/travagir/image/upload/v1/voyages/agadir_beach_1', 'voyages/agadir_beach_1', '2024-06-10 16:05:00', '2024-06-10 16:05:00'),
(6, 'https://res.cloudinary.com/travagir/image/upload/v1/voyages/agadir_beach_2', 'voyages/agadir_beach_2', '2024-06-10 16:06:00', '2024-06-10 16:06:00'),
(6, 'https://res.cloudinary.com/travagir/image/upload/v1/voyages/agadir_beach_3', 'voyages/agadir_beach_3', '2024-06-10 16:07:00', '2024-06-10 16:07:00'),
(7, 'https://res.cloudinary.com/travagir/image/upload/v1/voyages/chefchaouen_blue_1', 'voyages/chefchaouen_blue_1', '2024-07-05 12:35:00', '2024-07-05 12:35:00'),
(7, 'https://res.cloudinary.com/travagir/image/upload/v1/voyages/chefchaouen_blue_2', 'voyages/chefchaouen_blue_2', '2024-07-05 12:36:00', '2024-07-05 12:36:00'),
(8, 'https://res.cloudinary.com/travagir/image/upload/v1/voyages/ouarzazate_cinema_1', 'voyages/ouarzazate_cinema_1', '2024-08-12 15:50:00', '2024-08-12 15:50:00'),
(8, 'https://res.cloudinary.com/travagir/image/upload/v1/voyages/ouarzazate_cinema_2', 'voyages/ouarzazate_cinema_2', '2024-08-12 15:51:00', '2024-08-12 15:51:00');

-- =====================================================
-- 14. USER LOGINS (depends on users)
-- =====================================================
INSERT INTO user_logins (user_id, login_time, login_method, ip_address, user_agent) VALUES
(1, '2024-08-10 09:30:00', 'email', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0'),
(1, '2024-08-15 14:25:00', 'email', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0'),
(2, '2024-09-15 13:50:00', 'email', '192.168.1.101', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Safari/605.1.15'),
(2, '2024-09-20 09:55:00', 'email', '192.168.1.101', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Safari/605.1.15'),
(3, '2024-10-20 08:45:00', 'email', '192.168.1.102', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148'),
(3, '2024-10-25 16:30:00', 'email', '192.168.1.102', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148'),
(4, '2024-11-10 15:20:00', 'email', '192.168.1.103', 'Mozilla/5.0 (Linux; Android 14) Chrome/120.0.0.0'),
(4, '2024-11-15 09:15:00', 'email', '192.168.1.103', 'Mozilla/5.0 (Linux; Android 14) Chrome/120.0.0.0'),
(5, '2025-01-05 10:30:00', 'email', '192.168.1.104', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Firefox/121.0'),
(5, '2025-01-10 10:55:00', 'email', '192.168.1.104', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Firefox/121.0'),
(6, '2024-12-01 08:00:00', 'email', '192.168.1.105', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0'),
(6, '2024-12-15 14:30:00', 'email', '192.168.1.105', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0'),
(6, '2025-01-10 09:00:00', 'email', '192.168.1.105', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0'),
(7, '2024-12-15 12:45:00', 'email', '192.168.1.106', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Safari/605.1.15'),
(7, '2024-12-20 13:25:00', 'email', '192.168.1.106', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Safari/605.1.15'),
(8, '2025-02-20 14:50:00', 'email', '192.168.1.107', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148'),
(8, '2025-02-25 15:10:00', 'email', '192.168.1.107', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148'),
(1, '2025-03-15 10:20:00', 'email', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0'),
(1, '2025-03-20 10:25:00', 'email', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0');

-- =====================================================
-- 15. SEARCH HISTORY (depends on users)
-- =====================================================
INSERT INTO search_history (user_id, search_query, search_type, search_time, results_found) VALUES
(1, 'Marrakech desert adventure', 'voyage', '2024-08-10 09:35:00', 3),
(1, 'Sahara camel trekking', 'activity', '2024-08-10 09:40:00', 5),
(2, 'Marrakech cultural tour', 'voyage', '2024-09-15 13:55:00', 2),
(2, 'Medina walking tour', 'activity', '2024-09-15 14:00:00', 4),
(3, 'Atlas mountains trekking', 'voyage', '2024-10-20 08:50:00', 1),
(3, 'Mount Toubkal', 'activity', '2024-10-20 08:55:00', 2),
(4, 'Essaouira beach', 'voyage', '2024-11-10 15:25:00', 2),
(4, 'windsurfing lesson', 'activity', '2024-11-10 15:30:00', 3),
(5, 'Agadir beach resort', 'voyage', '2025-01-05 10:35:00', 4),
(5, 'surfing lesson', 'activity', '2025-01-05 10:40:00', 6),
(7, 'Fes historical tour', 'voyage', '2024-12-15 12:50:00', 1),
(7, 'tanneries visit', 'activity', '2024-12-15 12:55:00', 2),
(8, 'Chefchaouen blue city', 'voyage', '2025-02-20 14:55:00', 1),
(8, 'photography walk', 'activity', '2025-02-20 15:00:00', 3),
(1, 'Ouarzazate cinema', 'voyage', '2025-03-15 10:25:00', 1),
(1, 'film studios tour', 'activity', '2025-03-15 10:30:00', 2);

-- =====================================================
-- 16. VOYAGE VISITS (depends on users, voyages)
-- =====================================================
INSERT INTO voyage_visits (user_id, voyage_id, visit_time, source, view_duration_seconds) VALUES
(1, 1, '2024-08-10 09:32:00', 'search', 180),
(1, 1, '2024-08-15 14:26:00', 'direct', 240),
(2, 2, '2024-09-15 13:56:00', 'search', 300),
(2, 2, '2024-09-20 09:56:00', 'reservation', 120),
(3, 3, '2024-10-20 08:51:00', 'search', 420),
(3, 3, '2024-10-25 16:31:00', 'reservation', 180),
(4, 4, '2024-11-10 15:26:00', 'search', 360),
(4, 4, '2024-11-15 09:16:00', 'reservation', 90),
(4, 3, '2024-10-30 08:55:00', 'offer', 280),
(5, 6, '2025-01-05 10:36:00', 'search', 450),
(5, 6, '2025-01-10 10:56:00', 'reservation', 200),
(7, 5, '2024-12-15 12:51:00', 'search', 320),
(7, 5, '2024-12-20 13:26:00', 'reservation', 150),
(8, 7, '2025-02-20 14:56:00', 'search', 380),
(8, 7, '2025-02-25 15:11:00', 'reservation', 210),
(1, 8, '2025-03-15 10:26:00', 'search', 290),
(1, 8, '2025-03-20 10:26:00', 'reservation', 180),
(2, 1, '2024-09-11 11:50:00', 'offer', 200),
(3, 2, '2024-09-22 13:45:00', 'offer', 250),
(4, 3, '2024-10-28 16:00:00', 'offer', 310),
(5, 4, '2024-11-20 14:30:00', 'offer', 280);

-- =====================================================
-- 17. OFFER VIEWS (depends on users, offers)
-- =====================================================
INSERT INTO offer_views (user_id, offer_id, view_time, clicked) VALUES
(1, 1, '2024-08-10 09:33:00', TRUE),
(1, 1, '2024-08-15 14:27:00', FALSE),
(2, 2, '2024-09-15 13:57:00', TRUE),
(2, 2, '2024-09-20 09:57:00', FALSE),
(3, 3, '2024-10-20 08:52:00', TRUE),
(3, 3, '2024-10-25 16:32:00', FALSE),
(4, 4, '2024-11-10 15:27:00', TRUE),
(4, 4, '2024-11-15 09:17:00', FALSE),
(5, 6, '2025-01-05 10:37:00', TRUE),
(5, 6, '2025-01-10 10:57:00', FALSE),
(7, 5, '2024-12-15 12:52:00', TRUE),
(7, 5, '2024-12-20 13:27:00', FALSE),
(8, 7, '2025-02-20 14:57:00', TRUE),
(8, 7, '2025-02-25 15:12:00', FALSE),
(1, 8, '2025-03-15 10:27:00', TRUE),
(1, 8, '2025-03-20 10:27:00', FALSE),
(2, 9, '2024-09-11 11:51:00', TRUE),
(3, 10, '2024-09-22 13:46:00', TRUE),
(4, 3, '2024-10-28 16:01:00', TRUE),
(5, 4, '2024-11-20 14:31:00', TRUE);

-- =====================================================
-- End of Sample Data
-- =====================================================