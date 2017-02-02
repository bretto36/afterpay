<?php
namespace CultureKings\Afterpay\Service\InStore;

use CultureKings\Afterpay\Exception\ApiException;
use CultureKings\Afterpay\Model;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;

/**
 * Class Refund
 * @package CultureKings\Afterpay\Service\InStore
 */
class Refund extends AbstractService
{
    const ERROR_CONFLICT = 409;
    const ERROR_INVALID_ORDER_MERCHANT_REFERENCE = 412;
    const ERROR_PRECONDITION_FAILED = 412;
    const ERROR_INVALID_AMOUNT = 412;

    /**
     * @param Model\InStore\Refund $refund
     * @param HandlerStack|null    $stack
     *
     * @return Model\InStore\Refund|array|\JMS\Serializer\scalar|object
     */
    public function create(Model\InStore\Refund $refund, HandlerStack $stack = null)
    {
        try {
            $params = $this->generateParams(
                $refund,
                $stack
            );

            $result = $this->getClient()->post('refunds', $params);

            return $this->getSerializer()->deserialize(
                (string) $result->getBody(),
                Model\InStore\Refund::class,
                'json'
            );
        } catch (BadResponseException $exception) {
            throw new ApiException(
                $this->getSerializer()->deserialize(
                    (string) $exception->getResponse()->getBody(),
                    Model\ErrorResponse::class,
                    'json'
                ),
                $exception
            );
        }
    }

    /**
     * @param Model\InStore\Reversal $reversal
     * @param HandlerStack|null      $stack
     *
     * @return array|\JMS\Serializer\scalar|Model\InStore\Reversal
     */
    public function reverse(Model\InStore\Reversal $reversal, HandlerStack $stack = null)
    {
        try {
            $params = $this->generateParams(
                $reversal,
                $stack
            );

            $result = $this->getClient()->post('refunds/reverse', $params);

            return $this->getSerializer()->deserialize(
                (string) $result->getBody(),
                Model\InStore\Reversal::class,
                'json'
            );
        } catch (RequestException $e) {
            throw new ApiException(
                $this->getSerializer()->deserialize(
                    (string) $e->getResponse()->getBody(),
                    Model\ErrorResponse::class,
                    'json'
                ),
                $e
            );
        }
    }

    /**
     * Helper method to automatically attempt to reverse a refund if an error occurs.
     *
     * Refund reversal model does not have to be passed in and will be automatically generated if not.
     *
     * @param Model\InStore\Refund        $refund
     * @param Model\InStore\Reversal|null $refundReversal
     * @param HandlerStack|null           $stack
     *
     * @return array|\JMS\Serializer\scalar|object
     */
    public function createOrReverse(
        Model\InStore\Refund $refund,
        Model\InStore\Reversal $refundReversal = null,
        HandlerStack $stack = null
    ) {
        try {
            return $this->create($refund, $stack);
        } catch (ApiException $e) {
            // http://docs.afterpay.com.au/instore-api-v1.html#create-refund
            // Should a success or error response (with exception to 409 conflict) not be received,
            // the POS should queue the request ID for reversal
            if ($e->getErrorResponse()->getErrorCode() == self::ERROR_CONFLICT) {
                throw $e;
            }
        } catch (RequestException $e) {
            // a timeout or other exception has occurred. attempt a reversal
        }

        $now = new \DateTime();
        if ($refundReversal === null) {
            $refundReversal = new Model\InStore\Reversal();
            $refundReversal->setReversingRequestId($refund->getRequestId());
            $refundReversal->setRequestedAt($now);
        }

        return $this->reverse($refundReversal, $stack);
    }
}
