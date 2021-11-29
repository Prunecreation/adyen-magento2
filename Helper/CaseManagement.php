<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Sales\Model\Order;

/**
 * Helper class for anything related to Case Management (Manual Review)
 *
 * Class AdyenOrderPayment
 * @package Adyen\Payment\Helper
 */
class CaseManagement extends AbstractHelper
{
    const FRAUD_MANUAL_REVIEW = 'fraudManualReview';

    /**
     * @var AdyenLogger
     */
    private $adyenLogger;

    /**
     * @var Config
     */
    private $configHelper;

    /**
     * CaseManagement constructor.
     *
     * @param Context $context
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        Context $context,
        AdyenLogger $adyenLogger,
        Config $configHelper
    ) {
        parent::__construct($context);
        $this->adyenLogger = $adyenLogger;
        $this->configHelper = $configHelper;
    }

    /**
     * Based on the passed array, check if manual review is required
     *
     * @param array $additionalData
     * @return bool
     */
    public function requiresManualReview(array $additionalData): bool
    {
        if (!array_key_exists(self::FRAUD_MANUAL_REVIEW, $additionalData)) {
            return false;
        }

        // Strict comparison to 'true' since it will be sent as a string
        if ($additionalData[self::FRAUD_MANUAL_REVIEW] === 'true') {
            return true;
        }

        return false;
    }

    /**
     * Mark an order as pending manual review by adding a comment and also, update the status if the review status is set.
     *
     * @param Order $order
     * @param string $pspReference
     * @return Order
     */
    public function markCaseAsPendingReview(Order $order, string $pspReference): Order
    {
        $manualReviewComment = sprintf(
            'Manual review required for order w/ pspReference: %s',
            $pspReference
        );

        $reviewRequiredStatus = $this->configHelper->getFraudStatus(
            Config::XML_STATUS_FRAUD_MANUAL_REVIEW,
            $order->getStoreId()
        );

        if (!empty($reviewRequiredStatus)) {
            $order->addStatusHistoryComment(__($manualReviewComment), $reviewRequiredStatus);
            $this->adyenLogger->addAdyenNotificationCronjob(sprintf(
                'Ignore the pre authorized status for order %s since this is currently under manual review.' .
                'The following status will be set: %s',
                $order->getIncrementId(),
                $reviewRequiredStatus
            ));
        } else {
            $order->addStatusHistoryComment($manualReviewComment);
        }

        return $order;
    }

    /**
     * Mark a pending manual review order as accepted, by adding a comment and also update the status, if the review
     * accept status is set.
     *
     * @param Order $order
     * @param $comment
     * @return Order
     */
    public function markCaseAsAccepted(Order $order, $comment): Order
    {
        $reviewAcceptStatus = $this->configHelper->getFraudStatus(
            Config::XML_STATUS_FRAUD_MANUAL_REVIEW_ACCEPT,
            $order->getStoreId()
        );

        // Empty used to cater for empty string and null cases
        if (!empty($reviewAcceptStatus)) {
            $order->addStatusHistoryComment($comment, $reviewAcceptStatus);
            $this->adyenLogger->addAdyenNotificationCronjob(sprintf(
                'Created comment history for this notification linked to order %s with status update to: %s',
                $order->getIncrementId(),
                $reviewAcceptStatus
            ));
        } else {
            $order->addStatusHistoryComment($comment);
            $this->adyenLogger->addAdyenNotificationCronjob(sprintf(
                'Created comment history for this notification linked to order %s without any status update',
                $order->getIncrementId()
            ));
        }

        return $order;
    }
}