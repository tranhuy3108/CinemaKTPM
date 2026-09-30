<?php

/** @var AdminController $adminController */
if (!isset($adminController)) {
    throw new Exception('AdminController is required');
}

// Lấy các tham số filter
$currentPage = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$limit = 10;
$statusFilter = isset($_GET['status']) ? $_GET['status'] : null;
$search = isset($_GET['search']) ? $_GET['search'] : null;

// Lấy dữ liệu từ controller
$billsData = $adminController->getBillsPaginated($currentPage, $limit, $statusFilter, $search);
$stats = $adminController->getBillStats();

$bills = $billsData['data'];
$totalPages = $billsData['totalPages'];
$total = $billsData['total'];

// Helper function để format tiền
function formatMoney($amount)
{
    return number_format($amount, 0, ',', '.') . ' ₫';
}

// Helper function để lấy class CSS cho status
function getStatusClass($status)
{
    $classes = [
        'pending' => 'status-Pending',
        'paid' => 'status-Paid',
        'cancelled' => 'status-Cancelled',
        'refunded' => 'status-Refunded'
    ];
    return isset($classes[$status]) ? $classes[$status] : 'status-Pending';
}

// Helper function để lấy tên hiển thị cho status
function getStatusLabel($status)
{
    $labels = [
        'pending' => 'Chờ thanh toán',
        'paid' => 'Đã thanh toán',
        'cancelled' => 'Đã hủy',
        'refunded' => 'Đã hoàn tiền'
    ];
    return isset($labels[$status]) ? $labels[$status] : $status;
}
?>

<section class="bookings">

    <header class="admin-header">
        <h1>Quản lý đơn hàng</h1>
    </header>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success" style="margin-bottom: 20px; padding: 10px 15px; background: rgba(70, 211, 105, 0.1); border: 1px solid #46d369; border-radius: 8px; color: #46d369;">
            <?php
            $successMessages = [
                'confirmed' => 'Xác nhận thanh toán thành công!',
                'cancelled' => 'Hủy đơn hàng thành công!'
            ];
            $successMsg = isset($successMessages[$_GET['success']]) ? $successMessages[$_GET['success']] : 'Thao tác thành công!';
            echo htmlspecialchars($successMsg);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error" style="margin-bottom: 20px; padding: 10px 15px; background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; border-radius: 8px; color: #ef4444;">
            <?= htmlspecialchars($_GET['error']) ?>
        </div>
    <?php endif; ?>

    <div class="dashboard-content">

        <!-- Thống kê -->
        <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">

            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(255, 255, 255, 0.1);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    </svg>
                </div>
                <div class="stat-info">
                    <span class="stat-label">Tổng đơn</span>
                    <span class="stat-value"><?= $stats['total'] ?></span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(70, 211, 105, 0.1);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </div>
                <div class="stat-info">
                    <span class="stat-label">Đã thanh toán</span>
                    <span class="stat-value"><?= $stats['paid'] ?></span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(255, 165, 0, 0.1);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                </div>
                <div class="stat-info">
                    <span class="stat-label">Chờ thanh toán</span>
                    <span class="stat-value"><?= $stats['pending'] ?></span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(239, 68, 68, 0.1);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </div>
                <div class="stat-info">
                    <span class="stat-label">Đã hủy</span>
                    <span class="stat-value"><?= $stats['cancelled'] ?></span>
                </div>
            </div>

        </div>

        <!-- Bảng đơn hàng -->
        <div class="dashboard-card">

            <div class="card-header" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                <form class="search-form" method="GET" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                    <input type="hidden" name="page" value="bookings">

                    <input type="text" name="search" placeholder="Nhập mã đơn, tên, email..."
                        value="<?= htmlspecialchars(isset($search) ? $search : '') ?>" style="width: 250px;">

                    <select name="status" style="padding: 8px 12px; border-radius: 6px; border: 1px solid #333; background: #1a1a2e; color: #fff;">
                        <option value="">Tất cả trạng thái</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Chờ thanh toán</option>
                        <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Đã thanh toán</option>
                        <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Đã hủy</option>
                        <option value="refunded" <?= $statusFilter === 'refunded' ? 'selected' : '' ?>>Đã hoàn tiền</option>
                    </select>

                    <button type="submit" class="btn-primary" style="padding: 8px 16px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                        Tìm kiếm
                    </button>

                    <?php if ($search || $statusFilter): ?>
                        <a href="index.php?page=bookings" class="btn-secondary" style="padding: 8px 16px; text-decoration: none;">
                            Xóa bộ lọc
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Mã đơn</th>
                            <th>Khách hàng</th>
                            <th>Tổng tiền</th>
                            <th>Vé</th>
                            <th>Trạng thái</th>
                            <th>Ngày đặt</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bills)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: #888;">
                                    Không có đơn hàng nào
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bills as $bill): ?>

                                <?php
                                // ✅ LẤY TICKETS THEO BILL
                                $tickets = $ticketService->getTicketsByBillId($bill['bill_id']);

                                // ✅ LẤY COMBOS THEO BILL
                                $stmtC = $conn->prepare("
        SELECT bc.quantity, bc.price, c.name
        FROM bill_combos bc
        JOIN combos c ON c.combo_id = bc.combo_id
        WHERE bc.bill_id = ?
    ");
                                $stmtC->bind_param('i', $bill['bill_id']);
                                $stmtC->execute();
                                $res = $stmtC->get_result();

                                $combos = [];
                                while ($row = $res->fetch_assoc()) {
                                    $combos[] = $row;
                                }
                                ?>

                                <tr>
                                    <td><strong>#BK<?= str_pad($bill['bill_id'], 3, '0', STR_PAD_LEFT) ?></strong></td>

                                    <td>
                                        <div style="font-weight:bold;">
                                            <?= htmlspecialchars($bill['full_name']) ?>
                                        </div>
                                        <small style="color:#888;">
                                            <?= htmlspecialchars($bill['email']) ?>
                                        </small>
                                    </td>

                                    <td style="color: var(--primary-color); font-weight: bold;">
                                        <?= formatMoney($bill['final_amount']) ?>
                                    </td>

                                    <td><?= $bill['total_tickets'] ?> vé</td>

                                    <td>
                                        <span class="status-badge <?= getStatusClass($bill['status']) ?>">
                                            <?= getStatusLabel($bill['status']) ?>
                                        </span>
                                    </td>

                                    <td><?= date('H:i d/m/Y', strtotime($bill['created_at'])) ?></td>

                                    <td>
                                        <!-- VIEW DETAIL -->
                                        <button class="btn-action btn-view"
                                            onclick="openBillModal(this)"

                                            data-bill-id="<?= $bill['bill_id'] ?>"
                                            data-user="<?= htmlspecialchars($bill['full_name']) ?>"
                                            data-email="<?= htmlspecialchars($bill['email']) ?>"
                                            data-total="<?= $bill['final_amount'] ?>"

                                            data-tickets='<?= htmlspecialchars(json_encode($tickets), ENT_QUOTES) ?>'
                                            data-combos='<?= htmlspecialchars(json_encode($combos), ENT_QUOTES) ?>'>
                                            <i class="fas fa-eye"></i>
                                        </button>

                                        <?php if ($bill['status'] === 'pending'): ?>
                                            <?php if (hasPermission('bookings_update')): ?>
                                                <a href="index.php?page=bookings&action=confirm&id=<?= $bill['bill_id'] ?>"
                                                    class="action-btn btn-approve"
                                                    onclick="return confirm('Xác nhận đơn #BK<?= str_pad($bill['bill_id'], 3, '0', STR_PAD_LEFT) ?>?')">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                            <?php endif; ?>

                                            <?php if (hasPermission('bookings_delete')): ?>
                                                <a href="index.php?page=bookings&action=cancel&id=<?= $bill['bill_id'] ?>"
                                                    class="action-btn btn-cancel"
                                                    onclick="return confirm('Hủy đơn #BK<?= str_pad($bill['bill_id'], 3, '0', STR_PAD_LEFT) ?>?')">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color:#555;">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div id="billModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Chi tiết đơn hàng</h2>
                        <button class="btn-close" onclick="closeModal('billModal')">&times;</button>
                    </div>

                    <div class="modal-body" id="billDetailContent">
                    </div>

                    <div class="modal-footer">
                        <button class="btn-action" onclick="closeModal('billModal')">Đóng</button>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination" style="display: flex; justify-content: center; gap: 8px; padding: 20px;">
                    <?php
                    $queryParams = [];
                    if ($statusFilter) $queryParams['status'] = $statusFilter;
                    if ($search) $queryParams['search'] = $search;
                    $queryParams['page'] = 'bookings';
                    ?>

                    <?php if ($currentPage > 1): ?>
                        <a href="?<?= http_build_query(array_merge($queryParams, ['p' => $currentPage - 1])) ?>"
                            class="page-btn" style="padding: 8px 12px; background: #333; border-radius: 6px; color: #fff; text-decoration: none;">
                            &laquo; Trước
                        </a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?<?= http_build_query(array_merge($queryParams, ['p' => $i])) ?>"
                            class="page-btn <?= $i === $currentPage ? 'active' : '' ?>"
                            style="padding: 8px 12px; background: <?= $i === $currentPage ? 'var(--primary-color)' : '#333' ?>; border-radius: 6px; color: #fff; text-decoration: none;">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($currentPage < $totalPages): ?>
                        <a href="?<?= http_build_query(array_merge($queryParams, ['p' => $currentPage + 1])) ?>"
                            class="page-btn" style="padding: 8px 12px; background: #333; border-radius: 6px; color: #fff; text-decoration: none;">
                            Sau &raquo;
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

</section>