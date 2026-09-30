-- ============================================================================
-- SAMPLE DATA CHO BOOKINGS (BILLS) - 20 ĐƠN
-- Chạy file này sau khi đã có users trong database
-- ============================================================================

USE cinemax;

-- Thêm users mẫu (role_id = 2 là User)
INSERT INTO users (user_id, role_id, full_name, email, password_hash, phone, status) VALUES
(10, 2, 'Nguyễn Văn An', 'nguyenvanan@gmail.com', '$2y$10$abcdefghijklmnopqrstuv', '0901234567', 1),
(11, 2, 'Trần Thị Bình', 'tranthibinh@gmail.com', '$2y$10$abcdefghijklmnopqrstuv', '0912345678', 1),
(12, 2, 'Lê Văn Cường', 'levancuong@gmail.com', '$2y$10$abcdefghijklmnopqrstuv', '0923456789', 1),
(13, 2, 'Phạm Thị Dung', 'phamthidung@gmail.com', '$2y$10$abcdefghijklmnopqrstuv', '0934567890', 1),
(14, 2, 'Hoàng Văn Em', 'hoangvanem@gmail.com', '$2y$10$abcdefghijklmnopqrstuv', '0945678901', 1),
(15, 2, 'Vũ Thị Fương', 'vuthifuong@gmail.com', '$2y$10$abcdefghijklmnopqrstuv', '0956789012', 1),
(16, 2, 'Đặng Văn Giang', 'dangvangiang@gmail.com', '$2y$10$abcdefghijklmnopqrstuv', '0967890123', 1),
(17, 2, 'Bùi Thị Hoa', 'buithihoa@gmail.com', '$2y$10$abcdefghijklmnopqrstuv', '0978901234', 1),
(18, 2, 'Ngô Văn Khánh', 'ngovankhanh@gmail.com', '$2y$10$abcdefghijklmnopqrstuv', '0989012345', 1),
(19, 2, 'Lý Thị Lan', 'lythilan@gmail.com', '$2y$10$abcdefghijklmnopqrstuv', '0990123456', 1)
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

-- ============================================================================
-- THÊM 20 BILLS MẪU
-- ============================================================================

INSERT INTO bills (bill_id, user_id, total_tickets, total_amount, discount_amount, final_amount, promotion_id, status, created_at, paid_at) VALUES
-- Đơn đã thanh toán (8 đơn)
(1, 10, 2, 180000, 0, 180000, NULL, 'paid', '2026-03-20 19:30:00', '2026-03-20 19:35:00'),
(2, 11, 3, 270000, 27000, 243000, NULL, 'paid', '2026-03-20 20:15:00', '2026-03-20 20:20:00'),
(3, 12, 4, 360000, 0, 360000, NULL, 'paid', '2026-03-19 14:00:00', '2026-03-19 14:10:00'),
(4, 13, 2, 200000, 20000, 180000, NULL, 'paid', '2026-03-18 10:30:00', '2026-03-18 10:35:00'),
(5, 14, 2, 160000, 0, 160000, NULL, 'paid', '2026-03-17 15:00:00', '2026-03-17 15:05:00'),
(6, 15, 3, 300000, 30000, 270000, NULL, 'paid', '2026-03-16 20:00:00', '2026-03-16 20:10:00'),
(7, 18, 1, 90000, 0, 90000, NULL, 'paid', '2026-03-15 18:30:00', '2026-03-15 18:35:00'),
(8, 19, 4, 400000, 40000, 360000, NULL, 'paid', '2026-03-14 21:00:00', '2026-03-14 21:05:00'),

-- Đơn chờ thanh toán (6 đơn)
(9, 10, 2, 180000, 0, 180000, NULL, 'pending', '2026-04-02 08:00:00', NULL),
(10, 11, 1, 90000, 0, 90000, NULL, 'pending', '2026-04-02 09:15:00', NULL),
(11, 12, 5, 450000, 45000, 405000, NULL, 'pending', '2026-04-02 10:30:00', NULL),
(12, 16, 2, 180000, 0, 180000, NULL, 'pending', '2026-04-01 14:00:00', NULL),
(13, 17, 3, 270000, 0, 270000, NULL, 'pending', '2026-04-01 16:30:00', NULL),
(14, 18, 2, 200000, 20000, 180000, NULL, 'pending', '2026-04-01 19:00:00', NULL),

-- Đơn đã hủy (4 đơn)
(15, 13, 2, 180000, 0, 180000, NULL, 'cancelled', '2026-03-28 15:00:00', NULL),
(16, 14, 1, 90000, 0, 90000, NULL, 'cancelled', '2026-03-27 21:00:00', NULL),
(17, 19, 3, 270000, 0, 270000, NULL, 'cancelled', '2026-03-26 12:00:00', NULL),
(18, 10, 2, 180000, 0, 180000, NULL, 'cancelled', '2026-03-25 10:00:00', NULL),

-- Đơn đã hoàn tiền (2 đơn)
(19, 15, 3, 270000, 0, 270000, NULL, 'refunded', '2026-03-22 18:45:00', '2026-03-22 18:50:00'),
(20, 16, 2, 180000, 0, 180000, NULL, 'refunded', '2026-03-21 14:30:00', '2026-03-21 14:35:00')
ON DUPLICATE KEY UPDATE status = VALUES(status);

-- ============================================================================
-- KIỂM TRA KẾT QUẢ
-- ============================================================================

SELECT 
    b.bill_id,
    u.full_name,
    b.total_tickets,
    FORMAT(b.final_amount, 0) AS final_amount,
    b.status,
    DATE_FORMAT(b.created_at, '%d/%m/%Y %H:%i') AS created_at
FROM bills b
JOIN users u ON b.user_id = u.user_id
ORDER BY b.created_at DESC;

SELECT CONCAT('Tổng: ', COUNT(*), ' đơn (', 
    SUM(status='paid'), ' paid, ',
    SUM(status='pending'), ' pending, ',
    SUM(status='cancelled'), ' cancelled, ',
    SUM(status='refunded'), ' refunded)') AS summary 
FROM bills;
