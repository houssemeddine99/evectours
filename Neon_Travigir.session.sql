-- -- =====================================================
INSERT INTO user_logins
(user_id, login_time, login_method, ip_address, user_agent)
VALUES
(1, NOW(), 'email', '192.168.1.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'),
(2, NOW(), 'google', '41.225.10.5', 'Chrome/120.0 Mobile'),
(3, NOW(), 'facebook', '102.15.8.22', 'Safari/605.1.15'),
(1, NOW(), 'email', NULL, NULL);

SELECT * from user_logins ;

