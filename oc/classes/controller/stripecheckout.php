<?php

/**
* Stripe class
*
* @package Open Classifieds
* @subpackage Core
* @category Helper
* @author Chema Garrido <oliver@open-classifieds.com>
* @license GPL v3
*/

class Controller_StripeCheckout extends Controller{

    public function action_success()
    {
        $this->auto_render = FALSE;

        $id_order = $this->request->param('id');

        $order = (new Model_Order())->where('id_order', '=', $id_order)
                       ->where('status', '=', Model_Order::STATUS_CREATED)
                       ->limit(1)
                       ->find();

        if (! $order->loaded())
        {
            Alert::set(Alert::INFO, __('Order could not be loaded'));
            $this->redirect(Route::url('default'));
        }

        StripeCheckout::init();

        $stripe_payment_intent = \Stripe\PaymentIntent::retrieve($order->txn_id);

        if ($stripe_payment_intent->status != 'succeeded')
        {
            Alert::set(Alert::WARNING, 'We noticed you did not complete payment yet.');
            $this->redirect(Route::url('default', ['controller' => 'ad', 'action' => 'checkout', 'id' => $order->id_order]));
        }

        //mark as paid
        $order->confirm_payment('stripe', $stripe_payment_intent->id);

        $moderation = core::config('general.moderation');

        if ($moderation == Model_Ad::PAYMENT_MODERATION
            AND $order->id_product == Model_Order::PRODUCT_CATEGORY)
        {
            Alert::set(Alert::INFO, __('Advertisement is received, but first administrator needs to validate. Thank you for being patient!'));
            $this->redirect(Route::url('default', ['action' => 'thanks', 'controller' => 'ad', 'id' => $order->id_ad]));
        }

        if ($moderation == Model_Ad::PAYMENT_ON
            AND $order->id_product == Model_Order::PRODUCT_CATEGORY)

        {
            $this->redirect(Route::url('default', ['action' => 'thanks', 'controller' => 'ad', 'id' => $order->id_ad]));
        }

        //redirect him to his ads
        Alert::set(Alert::SUCCESS, __('Thanks for your payment!'));
        $this->redirect(Route::url('oc-panel', ['controller' => 'profile', 'action' => 'orders']));
    }

    public function action_thanks()
    {
        $id_order = $this->request->param('id');

        $order = (new Model_Order())->where('id_order', '=', $id_order)
           ->limit(1)
           ->find();

        $order->mark_as_pending_confirmation();

        $moderation = core::config('general.moderation');

        if ($moderation == Model_Ad::PAYMENT_MODERATION
            AND $order->id_product == Model_Order::PRODUCT_CATEGORY)
        {
            Alert::set(Alert::INFO, __('Advertisement is received, but first administrator needs to validate. Thank you for being patient!'));

            $this->redirect(Route::url('default', ['action' => 'thanks', 'controller' => 'ad', 'id' => $order->id_ad]));
        }

        if ($moderation == Model_Ad::PAYMENT_ON
            AND $order->id_product == Model_Order::PRODUCT_CATEGORY)
        {
            $this->redirect(Route::url('default', ['action' => 'thanks', 'controller' => 'ad', 'id' => $order->id_ad]));
        }

        Alert::set(Alert::SUCCESS, __('Thanks for your payment! We will process your payment and update your order status shortly.'));

        $this->redirect(Route::url('oc-panel', ['controller' => 'profile', 'action' => 'orders']));
    }

    public function action_success_connect()
    {
        $this->auto_render = FALSE;

        $id_order = $this->request->param('id');

        $order = (new Model_Order())->where('id_order', '=', $id_order)
                       ->where('status', '=', Model_Order::STATUS_CREATED)
                       ->where('id_product','in',[Model_Order::PRODUCT_AD_SELL, Model_Order::PRODUCT_AD_CUSTOM])
                       ->limit(1)
                       ->find();

        if (! $order->loaded())
        {
            Alert::set(Alert::INFO, __('Order could not be loaded'));
            $this->redirect(Route::url('default'));
        }

        StripeCheckout::init();

        $stripe_payment_intent = \Stripe\PaymentIntent::retrieve($order->txn_id);

        if ($stripe_payment_intent->status != 'succeeded')
        {
            Alert::set(Alert::WARNING, 'We noticed you did not complete payment yet.');
            $this->redirect(Route::url('default', ['controller' => 'ad', 'action' => 'checkout', 'id' => $order->id_order]));
        }

        //mark as paid
        $order->confirm_payment('stripe', $stripe_payment_intent->id);

        //only if is not admin
        if (! $order->ad->user->is_admin())
        {
            //crete new order for the application fee so we know how much the site owner is earning ;)
            $fee = NULL;

            if ($order->ad->user->subscription()->loaded())
            {
                $fee = $order->ad->user->subscription()->plan->marketplace_fee;
            }

            $application_fee = StripeKO::application_fee($order->amount, $fee);

            $order_app = Model_Order::new_order($order->ad, $order->ad->user,
                                                Model_Order::PRODUCT_APP_FEE, $application_fee, $order->currency,
                                                'id_order->'.$order->id_order.' id_ad->'.$order->ad->id_ad);
            $order_app->confirm_payment('stripe', $stripe_payment_intent->id);
        }

        Alert::set(Alert::SUCCESS, __('Thanks for your payment!'));
        $this->redirect(Route::url('oc-panel', array('controller'=>'profile','action'=>'orders')));
    }

    public function action_success_connect_guest()
    {
        $this->auto_render = FALSE;

        if (core::config('payment.stripe_connect') != 1)
        {
            Alert::set(Alert::INFO, __('Order could not be loaded'));
            $this->redirect(Route::url('default'));
        }

        $id_ad = $this->request->param('id');

        $ad = new Model_Ad($id_ad);

        if (! $ad->loaded())
        {
            Alert::set(Alert::INFO, __('Order could not be loaded'));
            $this->redirect(Route::url('default'));
        }

        if ($ad->status != Model_Ad::STATUS_PUBLISHED)
        {
            Alert::set(Alert::INFO, __('Order could not be loaded'));
            $this->redirect(Route::url('default'));
        }

        if (core::config('payment.stock') == 1 AND $ad->stock <= 0)
        {
            Alert::set(Alert::INFO, __('Order could not be loaded'));
            $this->redirect(Route::url('default'));
        }

        Alert::set(Alert::SUCCESS, __('Thanks for your payment!'));

        $this->redirect(Route::url('ad', ['category' => $ad->category->seoname, 'seotitle' => $ad->seotitle]));
    }

    public function action_webhook()
    {
        if (! core::config('payment.stripe_webhooks'))
        {
            throw HTTP_Exception::factory(400, 'Stripe webhooks are disabled');
        }

        StripeKO::init();

        $payload = Request::current()->body();

        $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'];

        $webhook_secret = core::config('payment.stripe_webhook_secret');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $signature, $webhook_secret
            );
        } catch(\UnexpectedValueException $e) {
            throw HTTP_Exception::factory(400, 'Invalid payload');
        } catch(\Stripe\Exception\SignatureVerificationException $e) {
            throw HTTP_Exception::factory(400, 'Invalid signature');
        }

        if ($event->type !== 'checkout.session.completed')
        {
            return 'Received unknown event type ' . $event->type;
        }

        $checkout_session = $event->data->object;

        $order = (new Model_Order())
            ->where('id_order', '=', $checkout_session->metadata->id_order)
            ->where('status', 'in', [Model_Order::STATUS_CREATED, Model_Order::STATUS_PENDING_CONFIRMATION])
            ->limit(1)
            ->find();

        if (! $order->loaded())
        {
            throw HTTP_Exception::factory(400, 'Invalid order id ' . $checkout_session->metadata->id_order);
        }

        $order->confirm_payment('stripe', $checkout_session->payment_intent);

        // process app fee
        if (
            Core::config('payment.stripe_connect')
            AND in_array($order->id_product, [Model_Order::PRODUCT_AD_SELL, Model_Order::PRODUCT_AD_CUSTOM])
            AND ! $order->ad->user->is_admin())
        {
            //crete new order for the application fee so we know how much the site owner is earning ;)
            $fee = NULL;

            if ($order->ad->user->subscription()->loaded())
            {
                $fee = $order->ad->user->subscription()->plan->marketplace_fee;
            }

            $application_fee = StripeKO::application_fee($order->amount, $fee);

            $order_app = Model_Order::new_order(
                $order->ad, $order->ad->user,
                Model_Order::PRODUCT_APP_FEE,
                $application_fee,
                $order->currency,
                'id_order->'.$order->id_order.' id_ad->'.$order->ad->id_ad
            );

            $order_app->confirm_payment('stripe', $checkout_session->payment_intent);
        }
    }
}
