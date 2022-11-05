<?php

namespace Blueoshan\HubspotConnector\Controller\Cart;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Checkout\Model\Session;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Framework\Exception\LocalizedException;

class RecoverCart extends Action
{
    private $checkoutSession;
    private $cartRepository;
    private $context;
    private $quoteIdMaskFactory;

    /**
     * RecoverCart constructor.
     * @param Context $context
     * @param Session $checkoutSession
     * @param CartRepositoryInterface $cartRepository
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        CartRepositoryInterface $cartRepository,
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        $this->context = $context;
        $this->checkoutSession = $checkoutSession;
        $this->cartRepository = $cartRepository;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        if(!$this->getRequest()->getParam('token')){
            throw new LocalizedException('No Cart token provided'); 
        }   
        $quoteId = $this->quoteIdMaskFactory->create()->load($this->getRequest()->getParam('token'), 'masked_id');
        $quote = $this->cartRepository->getActive($quoteId->getQuoteId());

        if ($quote !== null) {
            $this->checkoutSession->setQuoteId($quote->getId());
        } else {
            throw new LocalizedException('Sorry, we could not find your cart');
        }

	    return $this->_redirect('checkout/cart');
    }
}
