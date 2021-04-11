<?php

namespace Drupal\commerce_paytabs_pt2\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_paytabs_pt2\PluginForm\OffsiteRedirect\Paytabs_core;
use Drupal\commerce_paytabs_pt2\PluginForm\OffsiteRedirect\PaytabsApi;
use Drupal\commerce_paytabs_pt2\PluginForm\OffsiteRedirect\PaytabsEnum;
use Drupal\commerce_paytabs_pt2\PluginForm\OffsiteRedirect\PaytabsFollowupHolder;
use Drupal\commerce_paytabs_pt2\PluginForm\OffsiteRedirect\PaytabsTransactionOperationsHolder;
use Drupal\commerce_price\Price;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;
use Laminas\Diactoros\Response\JsonResponse;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;

/**
 * Provides the Off-site Redirect payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "paytabs_offsite_redirect",
 *   label = "Paytabs PT2",
 *   display_label = "Paytabs PT2",
 *   forms = {
 *     "offsite-payment" = "Drupal\commerce_paytabs_pt2\PluginForm\OffsiteRedirect\PaytabsOffsiteForm",
 *   },
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "visa", "mastercard",
 *   },
 * )
 */
class PaytabsOffsiteRedirect extends OffsitePaymentGatewayBase implements SupportsRefundsInterface
{
    /**
     * The logger.
     *
     * @var \Drupal\Core\Logger\LoggerChannelInterface
     */
    protected $logger;

    /**
     * The HTTP client.
     *
     * @var \GuzzleHttp\Client
     */
    protected $httpClient;

    /**
     * The time.
     *
     * @var \Drupal\Component\Datetime\TimeInterface
     */
    protected $time;

    /**
     * Module handler service.
     *
     * @var \Drupal\Core\Extension\ModuleHandlerInterface
     */
    protected $moduleHandler;


    /**
     * Constructs a new PaytabsOffsiteRedirect object.
     *
     * @param array $configuration
     *   A configuration array containing information about the plugin instance.
     * @param string $plugin_id
     *   The plugin_id for the plugin instance.
     * @param mixed $plugin_definition
     *   The plugin implementation definition.
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
     *   The entity type manager.
     * @param \Drupal\commerce_payment\PaymentTypeManager $payment_type_manager
     *   The payment type manager.
     * @param \Drupal\commerce_payment\PaymentMethodTypeManager $payment_method_type_manager
     *   The payment method type manager.
     * @param \Drupal\Component\Datetime\TimeInterface $time
     *   The time.
     * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_channel_factory
     *   The logger channel factory.
     * @param \GuzzleHttp\ClientInterface $client
     *   The client.
     * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
     *   The module handler.
     */
    public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PaymentTypeManager $payment_type_manager, PaymentMethodTypeManager $payment_method_type_manager, TimeInterface $time, LoggerChannelFactoryInterface $logger_channel_factory, ClientInterface $client, ModuleHandlerInterface $module_handler)
    {
        parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager, $time);

        $this->logger = $logger_channel_factory->get('commerce_paytabs_pt2');
        $this->httpClient = $client;
        $this->moduleHandler = $module_handler;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
    {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('entity_type.manager'),
            $container->get('plugin.manager.commerce_payment_type'),
            $container->get('plugin.manager.commerce_payment_method_type'),
            $container->get('datetime.time'),
            $container->get('logger.factory'),
            $container->get('http_client'),
            $container->get('module_handler')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration()
    {
        return [
                'profile_id' => '',
                'server_key' => '',
                'region' => '',
                'complete_order_status' => '',
            ] + parent::defaultConfiguration();
    }

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $form = parent::buildConfigurationForm($form, $form_state);

        $form['profile_id'] = [
            '#type' => 'number',
            '#title' => $this->t('Merchant Profile id'),
            '#required' => TRUE,
            '#description' => $this->t('Your merchant profile id , you can find the profile id on your ayTabs Merchant’s Dashboard- profile.'),
            '#default_value' => $this->configuration['profile_id'],
        ];
        $form['server_key'] = [
            '#type' => 'textfield',
            '#required' => TRUE,
            '#title' => $this->t('Server Key'),
            '#description' => $this->t('You can find the Server key on your PayTabs Merchant’s Dashboard - Developers - Key management.'),
            '#default_value' => $this->configuration['server_key'],
        ];
        $form['region'] = [
            '#type' => 'select',
            '#required' => TRUE,
            '#title' => $this->t('Merchant region'),
            '#description' => $this->t('The region you registered in with PayTabs'),
            '#options' => [
                'ARE' => $this->t('United Arab Emirates'),
                'EGY' => $this->t('Egypt'),
                'SAU' => $this->t('Saudi Arabia'),
                'OMN' => $this->t('Oman'),
                'JOR' => $this->t('Jordan'),
                'GLOBAL' => $this->t('GLOBAL'),
            ],
            '#default_value' => $this->configuration['region'],
        ];

        $form['complete_order_status'] = [
            '#type' => 'select',
            '#required' => TRUE,
            '#title' => $this->t('Order Status'),
            '#description' => $this->t('Order status after payment is done'),
            '#options' => [
                'completed' => $this->t('completed ' . "  '(this status is used when no action is needed )'  "),
                'fulfillment' => $this->t('fulfillment ' . "  '(if you select this option you should to use order fulfillment workflow)'  "),
                'validation' => $this->t('validation ' . "  '(if you select this option you should to use order default with validation workflow)'  "),
            ],
            '#default_value' => $this->configuration['complete_order_status'],
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        parent::validateConfigurationForm($form, $form_state);

        if (!$form_state->getErrors() && $form_state->isSubmitted()) {
            $values = $form_state->getValue($form['#parents']);
            $this->configuration['profile_id'] = $values['profile_id'];
            $this->configuration['server_key'] = $values['server_key'];
            $this->configuration['region'] = $values['region'];
            $this->configuration['complete_order_status'] = $values['complete_order_status'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        parent::submitConfigurationForm($form, $form_state);
        if (!$form_state->getErrors()) {
            $values = $form_state->getValue($form['#parents']);
            $this->configuration['profile_id'] = $values['profile_id'];
            $this->configuration['server_key'] = $values['server_key'];
            $this->configuration['region'] = $values['region'];
            $this->configuration['complete_order_status'] = $values['complete_order_status'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onReturn(OrderInterface $order, Request $request)
    {

        $all_response = $request->request->all();

        /**PayTabs SDK**/
        $paytabs_core = new Paytabs_core();
        $paytabs_api = PaytabsApi::getInstance($this->configuration['region'], $this->configuration['profile_id'], $this->configuration['server_key']);

        $is_valid = $paytabs_api->is_valid_redirect($all_response);

        if (!$is_valid) {
            $this->messenger()->addError($this->t('not valid result from PayTabs'));
        } else {
            $trans_ref = $request->request->get('tranRef');
            $respStatus = $request->request->get('respStatus');
            $this->logger->info('return Payment information. Transaction reference: ' . $trans_ref);
            if ($respStatus === 'A') {
                $message = 'Your payment was successful to payTabs with Transaction reference ';
                $payment_status = 'completed';
                $this->messenger()->addStatus($this->t($message . ' : %trans_ref', [
                    '%trans_ref' => $trans_ref,
                ]));
            } elseif ($respStatus === 'C') {
                $message = 'Your payment was Cancelled with Transaction reference ';
                $payment_status = 'cancelled';
                $this->messenger()->addError($this->t($message . ' : %trans_ref', [
                    '%trans_ref' => $trans_ref,
                ]));
            } else {
                $message = 'Your payment was '.$all_response['respMessage'].'with Transaction reference ';
                $payment_status = $all_response['respMessage'];
                $this->messenger()->addWarning($this->t($message . ' : %trans_ref', [
                    '%trans_ref' => $trans_ref,
                ]));
            }



            //Check if order don't have payments to insert it
            $query = \Drupal::entityQuery('commerce_payment')
                ->condition('order_id', $order->id())
                ->condition('remote_id', $trans_ref)
                ->condition('remote_state', $respStatus)
                ->execute();

            if (empty($query)) {
                $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
                $payment = $payment_storage->create([
                    'state' => $payment_status,
                    'amount' => $order->getTotalPrice(),
                    'payment_gateway' => $this->entityId,
                    'order_id' => $order->id(),
                    'remote_id' => $trans_ref,
                    'remote_state' => $respStatus,
                    'authorized' => $this->time->getRequestTime(),
                ]);
                $this->logger->info('Saving Payment information. Transaction reference: ' . $trans_ref);
                $payment->save();
                $this->logger->info('Payment information saved successfully. Transaction reference: ' . $trans_ref);

            }

            $order->set('state', $this->configuration['complete_order_status']);
            $order->save();

        }


    }

    /**
     * {@inheritdoc}
     */
    public function onNotify(Request $request)
    {
        $order_id = $request->request->get('cartId');
        $order = $this->entityTypeManager->getStorage('commerce_order')->load($order_id);


        $all_response = $request->request->all();

        /**PayTabs SDK**/
        $paytabs_core = new Paytabs_core();
        $paytabs_api = PaytabsApi::getInstance($this->configuration['region'], $this->configuration['profile_id'], $this->configuration['server_key']);

        $is_valid = $paytabs_api->is_valid_redirect($all_response);

        if (!$is_valid) {
            $this->messenger()->addError($this->t('not valid result from PayTabs'));
        } else {
            $trans_ref = $request->request->get('tranRef');
            $respStatus = $request->request->get('respStatus');
            $this->logger->info('return Payment information. Transaction reference: ' . $trans_ref);
            if ($respStatus === 'A') {
                $message = 'Your payment was successful to payTabs with Transaction reference ';
                $payment_status = 'completed';
                $this->messenger()->addStatus($this->t($message . ' : %trans_ref', [
                    '%trans_ref' => $trans_ref,
                ]));
            } elseif ($respStatus === 'C') {
                $message = 'Your payment was Cancelled with Transaction reference ';
                $payment_status = 'cancelled';
                $this->messenger()->addError($this->t($message . ' : %trans_ref', [
                    '%trans_ref' => $trans_ref,
                ]));
            } else {
                $message = 'Your payment was '.$all_response['respMessage'].'with Transaction reference ';
                $payment_status = $all_response['respMessage'];
                $this->messenger()->addWarning($this->t($message . ' : %trans_ref', [
                    '%trans_ref' => $trans_ref,
                ]));
            }



            //Check if order don't have payments to insert it
            $query = \Drupal::entityQuery('commerce_payment')
                ->condition('order_id', $order->id())
                ->condition('remote_id', $trans_ref)
                ->condition('remote_state', $respStatus)
                ->execute();

            if (empty($query)) {
                $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
                $payment = $payment_storage->create([
                    'state' => $payment_status,
                    'amount' => $order->getTotalPrice(),
                    'payment_gateway' => $this->entityId,
                    'order_id' => $order->id(),
                    'remote_id' => $trans_ref,
                    'remote_state' => $respStatus,
                    'authorized' => $this->time->getRequestTime(),
                ]);
                $this->logger->info('Saving Payment information. Transaction reference: ' . $trans_ref);
                $payment->save();
                $this->logger->info('Payment information saved successfully. Transaction reference: ' . $trans_ref);

            }
            $order->set('state', $this->configuration['complete_order_status']);
            $order->save();
            return new JsonResponse();
        }

    }

    /**
     * {@inheritdoc}
     */
    public function onCancel(OrderInterface $order, Request $request)
    {
        $this->messenger()->addError($this->t('You have canceled checkout at @gateway but may resume the checkout process here when you are ready.', [
            '@gateway' => $this->getDisplayLabel(),
        ]));
    }

    /**
     * {@inheritdoc}
     */
    public function refundPayment(PaymentInterface $payment, Price $amount = NULL)
    {
        $this->assertPaymentState($payment, ['completed', 'partially_refunded']);
        // If not specified, refund the entire amount.
        $amount = $amount ?: $payment->getAmount();
        $decimal_amount = $amount->getNumber();
        $currency_code = $payment->getAmount()->getCurrencyCode();
        $remote_id = $payment->getRemoteId();
        $cart_id = $payment->getOrder()->id();

        /**PayTabs SDK**/
        $paytabs_core = new Paytabs_core();
        $paytabs_api = PaytabsApi::getInstance($this->configuration['region'], $this->configuration['profile_id'], $this->configuration['server_key']);
        $refund = new PaytabsFollowupHolder();
        $this->assertRefundAmount($payment, $amount);

        // Perform the refund request here, throw an exception if it fails.
        try {
            $refund->set02Transaction(PaytabsEnum::TRAN_TYPE_REFUND, PaytabsEnum::TRAN_CLASS_ECOM)
                ->set03Cart($cart_id, $currency_code, $decimal_amount, 'refunded from drupal')
                ->set30TransactionInfo($remote_id);

            $refund_params = $refund->pt_build();
            $result = $paytabs_api->request_followup($refund_params);

            $success = $result->success;
            $message = $result->message;
            $pending_success = $result->pending_success;

            if ($success) {
                // Determine whether payment has been fully or partially refunded.
                $old_refunded_amount = $payment->getRefundedAmount();
                $new_refunded_amount = $old_refunded_amount->add($amount);
                if ($new_refunded_amount->lessThan($payment->getAmount())) {
                    $payment->setState('partially_refunded');
                } else {
                    $payment->setState('refunded');
                }
                $payment->setRefundedAmount($new_refunded_amount);
                $payment->save();
            } else if ($pending_success) {
                $this->messenger()->addError($this->t('not valid result from PayTabs'));
            }
        } catch (\Exception $e) {
            $this->logger->log('error', 'failed to proceed to refund transaction:' . $remote_id);
            throw new PaymentGatewayException($e);
        }

    }


    public function getShippingInfo(OrderInterface $order)
    {

        if (!$this->moduleHandler->moduleExists('commerce_shipping')) {
            return [];
        } else {
            // Check if the order references shipments.
            if ($order->hasField('shipments') && !$order->get('shipments')->isEmpty()) {
                $shipping_profiles = [];

                // Loop over the shipments to collect shipping profiles.
                foreach ($order->get('shipments')->referencedEntities() as $shipment) {
                    if ($shipment->get('shipping_profile')->isEmpty()) {
                        continue;
                    }
                    $shipping_profile = $shipment->getShippingProfile();
                    $shipping_profiles[$shipping_profile->id()] = $shipping_profile;
                }

                if ($shipping_profiles && count($shipping_profiles) === 1) {
                    $shipping_profile = reset($shipping_profiles);
                    /** @var \Drupal\address\AddressInterface $address */
                    $address = $shipping_profile->address->first();
                    $shipping_info = [
                        'shipping_first_name' => $address->getGivenName(),
                        'shipping_last_name' => $address->getFamilyName(),
                        'address_shipping' => $address->getAddressLine1(),
                        'city_shipping' => $address->getLocality(),
                        'state_shipping' => $address->getAdministrativeArea(),
                        'postal_code_shipping' => $address->getPostalCode(),
                        'country_shipping' => \Drupal::service('address.country_repository')->get($address->getCountryCode())->getThreeLetterCode(),
                    ];
                }
                return $shipping_info;
            }
        }
    }

    public function getPaytabsEntity()
    {
        return $this->entityId;
    }
}
