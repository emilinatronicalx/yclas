<?php defined('SYSPATH') or die('No direct script access.');?>

<div class="page-header">
    <h1><?=_e('Orders')?></h1>
</div>

<div class="panel panel-default">
    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?=_e('Status') ?></th>
                    <th><?=_e('Product') ?></th>
                    <th><?=_e('Amount') ?></th>
                    <th><?=_e('Ad') ?></th>
                    <th><?=_e('Date') ?></th>
                    <th><?=_e('Date Paid') ?></th>
                    <th><?=_e('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?foreach($orders as $order):?>
                    <tr id="tr<?=$order->pk()?>">

                        <td><?=$order->pk()?></td>

                        <td>
                            <?
                                $status = [
                                    Model_Order::STATUS_CREATED =>  __('Created'),
                                    Model_Order::STATUS_PAID =>  __('Paid'),
                                    Model_Order::STATUS_REFUSED =>  __('Refused'),
                                    Model_Order::STATUS_REFUND =>  __('Refund'),
                                    Model_Order::STATUS_PENDING_CONFIRMATION =>  __('Pending confirmation'),
                                ]
                            ?>

                            <?= $status[$order->status] ?>
                        </td>

                        <td><?=Model_Order::product_desc($order->id_product)?></td>

                        <td><?=i18n::format_currency($order->amount, $order->currency)?></td>

                        <td><a href="<?=Route::url('ad', array('category'=> $order->ad->category->seoname,'seotitle'=>$order->ad->seotitle)) ?>" title="<?=HTML::chars($order->ad->title)?>">
                            <?=Text::limit_chars($order->ad->title, 30, NULL, TRUE)?></a></td>

                        <td><?=$order->created?></td>

                        <td><?=$order->pay_date?></td>

                        <td>
                            <?if ($order->status == Model_Order::STATUS_CREATED AND $order->paymethod != 'escrow'):?>
                                <a class="btn btn-warning" href="<?=Route::url('default', array('controller'=> 'ad','action'=>'checkout' , 'id' => $order->id_order))?>">
                                <i class="glyphicon glyphicon-shopping-cart"></i> <?=_e('Pay')?>   
                                </a>
                            <?elseif ($order->status == Model_Order::STATUS_CREATED AND $order->paymethod == 'escrow'):?>
                                <? $transaction = json_decode($order->txn_id) ?>
                                <a class="btn btn-warning" href="<?= $transaction->landing_page ?>">
                                    <i class="glyphicon glyphicon-shopping-cart"></i> <?=_e('Pay')?>   
                                </a>
                                <a class="btn btn-default" href="<?= Route::url('default', ['controller'=>'escrow', 'action'=>'paid', 'id' => $order->id_order]) ?>">
                                    <i class="glyphicon glyphicon-check"></i> <?=_e('Mark as paid')?>   
                                </a>
                            <?else:?>
                                <a class="btn btn-default" href="<?=Route::url('oc-panel', array('controller'=>'profile', 'action'=>'order', 'id' => $order->id_order))?>">
                                    <i class="fa fa-search"></i> <?=_e('View')?>   
                                </a>

                                <?if (core::config('general.ewallet') AND $order->received === NULL AND $order->status == Model_Order::STATUS_PAID AND in_array($order->id_product, [Model_Order::PRODUCT_AD_SELL, Model_Order::PRODUCT_AD_CUSTOM])):?>
                                    <a class="btn btn-warning" href="<?=Route::url('oc-panel', array('controller'=> 'profile','action'=>'order_received' , 'id' => $order->id_order))?>">
                                        <i class="fa fa-hands"></i> <?=_e('Mark as received')?>   
                                    </a>
                                <?endif?>
                            <?endif?>

                            <?if ($order->paymethod == 'escrow'):?>
                                <? $transaction = json_decode($order->txn_id) ?>

                                <?if (isset($transaction->status) AND $transaction->status->shipped AND ! $transaction->status->received):?>
                                    <a class="btn btn-default" href="<?= Route::url('oc-panel', ['controller'=>'escrow', 'action'=>'receive', 'id' => $order->id_order]) ?>">
                                        <i class="glyphicon glyphicon-check"></i> <?=_e('Mark as received')?>
                                    </a>
                                <?endif?>

                                <?if (isset($transaction->status) AND $transaction->status->received AND ! $transaction->status->accepted):?>
                                    <a class="btn btn-default" href="<?= Route::url('oc-panel', ['controller'=>'escrow', 'action'=>'accept', 'id' => $order->id_order]) ?>">
                                        <i class="glyphicon glyphicon-check"></i> <?=_e('Mark as accepted')?>
                                    </a>
                                <?endif?>
                            <?endif?>

                            <?if (Core::config('payment.stripe_connect') AND core::config('payment.stripe_escrow')):?>
                                <?if ($order->shipped !== NULL AND $order->received === NULL):?>
                                    <a class="ml-2 btn btn-default" href="<?= Route::url('oc-panel', ['controller'=>'profile', 'action'=>'order_received', 'id' => $order->id_order]) ?>">
                                        <i class="fas fa-check"></i> <?=_e('Mark as received')?>
                                    </a>
                                <?endif?>

                                <?if ($order->cancelled === NULL AND $order->shipped === NULL):?>
                                    <a class="ml-2 btn btn-default" href="<?= Route::url('oc-panel', ['controller'=>'profile', 'action'=>'cancel_order', 'id' => $order->id_order]) ?>">
                                        <i class="fas fa-times"></i> <?=_e('Cancel order')?>
                                    </a>
                                <?endif?>
                            <?endif?>
                        </td>

                    </tr>
                <?endforeach?>
            </tbody>
        </table>
    </div>
</div>
<div class="text-center"><?=$pagination?></div>
