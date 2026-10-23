<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model\Payment;

/**
 * Marker for the store credit payment method.
 *
 * The method itself is a virtual type of Magento\Payment\Model\Method\Adapter declared in di.xml —
 * the supported way to add an offline method since AbstractMethod was deprecated. This class name
 * is only what config.xml points at so the method appears in the payment configuration.
 */
class StoreCreditFacade
{
    public const CODE = 'tonyapps_b2b_credit';
}
