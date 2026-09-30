<?php

require_once __DIR__ . '/../models/Ticket.php';

class TicketService
{
    private $ticketModel;

    public function __construct($ticketModel)
    {
        $this->ticketModel = $ticketModel;
    }

    /**
     * Lấy tất cả ticket
     */
    public function getAllTickets(): array
    {
        return $this->ticketModel->getAllTickets();
    }

    /**
     * Lấy ticket theo suất chiếu
     */
    public function getTicketByShowId($show_id): array
    {
        if (empty($show_id)) {
            throw new InvalidArgumentException(
                'Show ID không hợp lệ.'
            );
        }

        return $this->ticketModel->getTicketByShowId(
            (int)$show_id
        );
    }

    /**
     * Tạo ticket cho toàn bộ ghế của suất chiếu
     *
     * Lưu ý:
     * Transaction do ShowService quản lý.
     * Method này không tự begin/commit transaction.
     */
    public function createTicket($show_id): int
    {
        if (empty($show_id)) {
            throw new InvalidArgumentException(
                'Show không tồn tại.'
            );
        }

        $show_id = (int)$show_id;

        $status = 'available';
        $bill_id = null;

        // ĐÚNG TÊN METHOD
        $seats = $this->ticketModel->getAllSeatsByShow(
            $show_id
        );

        if (empty($seats)) {
            throw new InvalidArgumentException(
                'Không có ghế nào trong suất chiếu này.'
            );
        }

        $createdCount = 0;

        foreach ($seats as $seat) {
            if (
                !isset($seat['seat_id']) ||
                !isset($seat['base_price']) ||
                !isset($seat['price_multiplier'])
            ) {
                throw new RuntimeException(
                    'Thông tin ghế không đầy đủ.'
                );
            }

            $price =
                (float)$seat['base_price']
                *
                (float)$seat['price_multiplier'];

            $this->ticketModel->createTicket(
                $show_id,
                (int)$seat['seat_id'],
                $bill_id,
                $price,
                $status
            );

            $createdCount++;
        }

        return $createdCount;
    }

    /**
     * Cập nhật trạng thái một ticket
     */
    public function updateStatusTicket(
        $ticket_id,
        $status
    ): bool {
        if (empty($ticket_id)) {
            throw new InvalidArgumentException(
                'Ticket ID không hợp lệ.'
            );
        }

        $allowedStatuses = [
            'available',
            'booked',
            'paid',
            'used',
            'cancelled'
        ];

        if (!in_array($status, $allowedStatuses, true)) {
            throw new InvalidArgumentException(
                'Trạng thái ticket không hợp lệ.'
            );
        }

        return $this->ticketModel->updateStatusTicket(
            (int)$ticket_id,
            $status
        );
    }

    /**
     * Cập nhật trạng thái toàn bộ ticket theo bill
     */
    public function updateStatusByBillId(
        $bill_id,
        $status
    ): bool {
        if (empty($bill_id)) {
            throw new InvalidArgumentException(
                'Bill ID không hợp lệ.'
            );
        }

        $allowedStatuses = [
            'available',
            'booked',
            'paid',
            'used',
            'cancelled'
        ];

        if (!in_array($status, $allowedStatuses, true)) {
            throw new InvalidArgumentException(
                'Trạng thái ticket không hợp lệ.'
            );
        }

        return $this->ticketModel->updateStatusByBillId(
            (int)$bill_id,
            $status
        );
    }

    /**
     * Lấy ticket theo bill
     */
    public function getTicketsByBillId($billId): array
    {
        if (empty($billId)) {
            throw new InvalidArgumentException(
                'Bill ID không hợp lệ.'
            );
        }

        return $this->ticketModel->getTicketsByBillId(
            (int)$billId
        );
    }
}