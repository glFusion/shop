<?php
/**
 * Define order states.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Models;

/**
 * The state of the order.
 * @package shop
 */
class OrderState
{
    /** Order is still in the shopping-cart phase.
     */
    public const CART = 'cart';

    /** Indicates the order is open. Open orders may be updated.
     */
    public const PENDING = 'pending';

    /** Order has been invoiced and is awaiting payment.
     */
    public const INVOICED = 'invoiced';

    /** Order is in-process.
     */
    public const PROCESSING = 'processing';

    /** Order has been shipped complete.
     */
    public const SHIPPED = 'shipped';

    /** Order has been refunded.
     */
    public const REFUNDED = 'refunded';

    /** Order is complete and paid. No further action needed.
     */
    public const CLOSED = 'closed';

    /** Payment received.
     * Not really an order status, but may be used for logging.
     */
    public const PAID = 'paid';

    /** Order was cancelled.
     */
    public const CANCELED = 'canceled';
}
