<?php
namespace App\Service;

use App\Entity\Sepas;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use OnlinePayments\Sdk\Client;
use OnlinePayments\Sdk\Communicator;
use OnlinePayments\Sdk\CommunicatorConfiguration;
use OnlinePayments\Sdk\DefaultConnection;
use OnlinePayments\Sdk\Domain\AmountOfMoney;
use OnlinePayments\Sdk\Domain\CreateMandateRequest;
use OnlinePayments\Sdk\Domain\CreatePaymentRequest;
use OnlinePayments\Sdk\Domain\MandatePersonalName;
use OnlinePayments\Sdk\Domain\SepaDirectDebitPaymentMethodSpecificInput;
use stdClass;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class WorldlineService {
    protected EntityManagerInterface $entityManager;
    protected ParameterBagInterface $parameterBag;

    public function __construct(EntityManagerInterface $entityManager, ParameterBagInterface $parameterBag) {
        $this->entityManager = $entityManager;
        $this->parameterBag = $parameterBag;
    }

    public function make(Sepas $sepas) {
        $connection = new DefaultConnection();

// Your PSPID in either our test or live environment
        $merchantId = "EVITELLESSDD";

// Put the value of the API Key which you can find in the Merchant Portal
// https://secure.ogone.com/Ncol/Test/Backoffice/login/
        $apiKey = $this->parameterBag->get('worldline_public');

// Put the value of the API Secret which you can find in the Merchant Portal
// https://secure.ogone.com/Ncol/Prod/BackOffice/login/
        $apiSecret = $this->parameterBag->get('worldline_secret');

// This endpoint is pointing to the TEST server
// Note: Use the endpoint without the /v2/ part here
        $apiEndpoint = 'https://payment.direct.worldline-solutions.com';

// Additional settings to easily identify your company in our logs.
        $integrator = 'Evitelles';

        $proxyConfiguration = null;
        /*
        * To use proxy, you should uncomment the section below
        * and replace proper settings with your settings of the proxy.
        * (additionally, you can comment on the previous setting).
        */
        /*
        $proxyConfiguration = new ProxyConfiguration(
            'proxyHost',
            'proxyPort',
            'proxyUserName',
            'proxyPassword'
        );
        */
        $communicatorConfiguration = new CommunicatorConfiguration(
            $apiKey,
            $apiSecret,
            $apiEndpoint,
            $integrator,
            $proxyConfiguration
        );

        $communicator = new Communicator($connection, $communicatorConfiguration);

        $client = new Client($communicator);

        $merchantClient = $client->merchant($merchantId);

        $customer = $sepas->getCustomer();
        /*
         * The PaymentsClient object based on the MerchantClient
         * object created in initialisation
         */
        $paymentsClient = $merchantClient->payments();

        $createPaymentRequest = new CreatePaymentRequest();

        $order = new \OnlinePayments\Sdk\Domain\Order();
// Example object of the AmountOfMoney
        $amountOfMoney = new AmountOfMoney();
        $amountOfMoney->setCurrencyCode("EUR");
        $amountOfMoney->setAmount($sepas->getAmount() * 100);
        $order->setAmountOfMoney($amountOfMoney);

        $createPaymentRequest->setOrder($order);

        $sepaPaymentMethodSpecificInput = new SepaDirectDebitPaymentMethodSpecificInput();
        $sepaPaymentMethodSpecificInput->setPaymentProductId(771);

        $mandate = new CreateMandateRequest();
        $mandate->setRecurrenceType('UNIQUE');
        $mandate->setSignatureType('UNSIGNED');
        $mandate->setCustomerReference((string)$customer->getId());
        $mandate->setUniqueMandateReference(substr(uniqid(), 0, 10));
        $mandate->setAlias('mandateAlias');
        $mandateCustomer = new \OnlinePayments\Sdk\Domain\MandateCustomer();
        $bankAccountIban = new \OnlinePayments\Sdk\Domain\BankAccountIban();
        $bankAccountIban->setIban(str_replace(' ', '', $sepas->getRib()));
        $mandateCustomer->setBankAccountIban($bankAccountIban);
        $mandateAddress = new \OnlinePayments\Sdk\Domain\MandateAddress();
        $array = array(0,1,2,3,4,5,6,7,8,9);
        $customer_address1 = str_replace($array,'', $customer->getAddress1());
        $customer_city = str_replace($array,'', $customer->getCity());
        $mandateAddress->setStreet($this->remove_accents($customer_address1));
        $mandateAddress->setCity($this->remove_accents($customer_city));
        $mandateAddress->setCountryCode('FR');
        $mandateAddress->setHouseNumber('0');
        $mandateAddress->setZip($customer->getZipcode());
        $mandateCustomer->setMandateAddress($mandateAddress);
        $mandatePi = new \OnlinePayments\Sdk\Domain\MandatePersonalInformation();
        $mandatePn = new MandatePersonalName();

        $nom = $customer->getLastname();
        $prenom = $customer->getFirstname();

        $mandatePn->setFirstName($this->remove_accents($prenom));
        $mandatePn->setSurname($this->remove_accents($nom));
        $mandatePi->setName($mandatePn);
        $mandatePi->setTitle('Mr');
        $mandateCustomer->setPersonalInformation($mandatePi);
        $mandate->setCustomer($mandateCustomer);

        $paymentProduct = new \OnlinePayments\Sdk\Domain\SepaDirectDebitPaymentProduct771SpecificInput();
        $paymentProduct->setMandate($mandate);
        $sepaPaymentMethodSpecificInput->setPaymentProduct771SpecificInput($paymentProduct);

        $createPaymentRequest->setSepaDirectDebitPaymentMethodSpecificInput($sepaPaymentMethodSpecificInput);

        try {
            $createPaymentResponse = $paymentsClient->createPayment($createPaymentRequest);
        } catch(Exception $exception) {
            return ['errors' => $exception->getResponse()->getErrors()];
        }

        $capturePayment = new \OnlinePayments\Sdk\Domain\CapturePaymentRequest();
        $capturePayment->setAmount(100);
        $capturePayment->setIsFinal(true);

        try {
            $capture = $paymentsClient->capturePayment($createPaymentResponse->getPayment()->getId(), $capturePayment);
        } catch(\Exception $e) {

        }

    }

    function remove_accents($string) {
        if ( !preg_match('/[\x80-\xff]/', $string) )
            return $string;

        $chars = array(
            // Decompositions for Latin-1 Supplement
            chr(195).chr(128) => 'A', chr(195).chr(129) => 'A',
            chr(195).chr(130) => 'A', chr(195).chr(131) => 'A',
            chr(195).chr(132) => 'A', chr(195).chr(133) => 'A',
            chr(195).chr(135) => 'C', chr(195).chr(136) => 'E',
            chr(195).chr(137) => 'E', chr(195).chr(138) => 'E',
            chr(195).chr(139) => 'E', chr(195).chr(140) => 'I',
            chr(195).chr(141) => 'I', chr(195).chr(142) => 'I',
            chr(195).chr(143) => 'I', chr(195).chr(145) => 'N',
            chr(195).chr(146) => 'O', chr(195).chr(147) => 'O',
            chr(195).chr(148) => 'O', chr(195).chr(149) => 'O',
            chr(195).chr(150) => 'O', chr(195).chr(153) => 'U',
            chr(195).chr(154) => 'U', chr(195).chr(155) => 'U',
            chr(195).chr(156) => 'U', chr(195).chr(157) => 'Y',
            chr(195).chr(159) => 's', chr(195).chr(160) => 'a',
            chr(195).chr(161) => 'a', chr(195).chr(162) => 'a',
            chr(195).chr(163) => 'a', chr(195).chr(164) => 'a',
            chr(195).chr(165) => 'a', chr(195).chr(167) => 'c',
            chr(195).chr(168) => 'e', chr(195).chr(169) => 'e',
            chr(195).chr(170) => 'e', chr(195).chr(171) => 'e',
            chr(195).chr(172) => 'i', chr(195).chr(173) => 'i',
            chr(195).chr(174) => 'i', chr(195).chr(175) => 'i',
            chr(195).chr(177) => 'n', chr(195).chr(178) => 'o',
            chr(195).chr(179) => 'o', chr(195).chr(180) => 'o',
            chr(195).chr(181) => 'o', chr(195).chr(182) => 'o',
            chr(195).chr(182) => 'o', chr(195).chr(185) => 'u',
            chr(195).chr(186) => 'u', chr(195).chr(187) => 'u',
            chr(195).chr(188) => 'u', chr(195).chr(189) => 'y',
            chr(195).chr(191) => 'y',
            // Decompositions for Latin Extended-A
            chr(196).chr(128) => 'A', chr(196).chr(129) => 'a',
            chr(196).chr(130) => 'A', chr(196).chr(131) => 'a',
            chr(196).chr(132) => 'A', chr(196).chr(133) => 'a',
            chr(196).chr(134) => 'C', chr(196).chr(135) => 'c',
            chr(196).chr(136) => 'C', chr(196).chr(137) => 'c',
            chr(196).chr(138) => 'C', chr(196).chr(139) => 'c',
            chr(196).chr(140) => 'C', chr(196).chr(141) => 'c',
            chr(196).chr(142) => 'D', chr(196).chr(143) => 'd',
            chr(196).chr(144) => 'D', chr(196).chr(145) => 'd',
            chr(196).chr(146) => 'E', chr(196).chr(147) => 'e',
            chr(196).chr(148) => 'E', chr(196).chr(149) => 'e',
            chr(196).chr(150) => 'E', chr(196).chr(151) => 'e',
            chr(196).chr(152) => 'E', chr(196).chr(153) => 'e',
            chr(196).chr(154) => 'E', chr(196).chr(155) => 'e',
            chr(196).chr(156) => 'G', chr(196).chr(157) => 'g',
            chr(196).chr(158) => 'G', chr(196).chr(159) => 'g',
            chr(196).chr(160) => 'G', chr(196).chr(161) => 'g',
            chr(196).chr(162) => 'G', chr(196).chr(163) => 'g',
            chr(196).chr(164) => 'H', chr(196).chr(165) => 'h',
            chr(196).chr(166) => 'H', chr(196).chr(167) => 'h',
            chr(196).chr(168) => 'I', chr(196).chr(169) => 'i',
            chr(196).chr(170) => 'I', chr(196).chr(171) => 'i',
            chr(196).chr(172) => 'I', chr(196).chr(173) => 'i',
            chr(196).chr(174) => 'I', chr(196).chr(175) => 'i',
            chr(196).chr(176) => 'I', chr(196).chr(177) => 'i',
            chr(196).chr(178) => 'IJ',chr(196).chr(179) => 'ij',
            chr(196).chr(180) => 'J', chr(196).chr(181) => 'j',
            chr(196).chr(182) => 'K', chr(196).chr(183) => 'k',
            chr(196).chr(184) => 'k', chr(196).chr(185) => 'L',
            chr(196).chr(186) => 'l', chr(196).chr(187) => 'L',
            chr(196).chr(188) => 'l', chr(196).chr(189) => 'L',
            chr(196).chr(190) => 'l', chr(196).chr(191) => 'L',
            chr(197).chr(128) => 'l', chr(197).chr(129) => 'L',
            chr(197).chr(130) => 'l', chr(197).chr(131) => 'N',
            chr(197).chr(132) => 'n', chr(197).chr(133) => 'N',
            chr(197).chr(134) => 'n', chr(197).chr(135) => 'N',
            chr(197).chr(136) => 'n', chr(197).chr(137) => 'N',
            chr(197).chr(138) => 'n', chr(197).chr(139) => 'N',
            chr(197).chr(140) => 'O', chr(197).chr(141) => 'o',
            chr(197).chr(142) => 'O', chr(197).chr(143) => 'o',
            chr(197).chr(144) => 'O', chr(197).chr(145) => 'o',
            chr(197).chr(146) => 'OE',chr(197).chr(147) => 'oe',
            chr(197).chr(148) => 'R',chr(197).chr(149) => 'r',
            chr(197).chr(150) => 'R',chr(197).chr(151) => 'r',
            chr(197).chr(152) => 'R',chr(197).chr(153) => 'r',
            chr(197).chr(154) => 'S',chr(197).chr(155) => 's',
            chr(197).chr(156) => 'S',chr(197).chr(157) => 's',
            chr(197).chr(158) => 'S',chr(197).chr(159) => 's',
            chr(197).chr(160) => 'S', chr(197).chr(161) => 's',
            chr(197).chr(162) => 'T', chr(197).chr(163) => 't',
            chr(197).chr(164) => 'T', chr(197).chr(165) => 't',
            chr(197).chr(166) => 'T', chr(197).chr(167) => 't',
            chr(197).chr(168) => 'U', chr(197).chr(169) => 'u',
            chr(197).chr(170) => 'U', chr(197).chr(171) => 'u',
            chr(197).chr(172) => 'U', chr(197).chr(173) => 'u',
            chr(197).chr(174) => 'U', chr(197).chr(175) => 'u',
            chr(197).chr(176) => 'U', chr(197).chr(177) => 'u',
            chr(197).chr(178) => 'U', chr(197).chr(179) => 'u',
            chr(197).chr(180) => 'W', chr(197).chr(181) => 'w',
            chr(197).chr(182) => 'Y', chr(197).chr(183) => 'y',
            chr(197).chr(184) => 'Y', chr(197).chr(185) => 'Z',
            chr(197).chr(186) => 'z', chr(197).chr(187) => 'Z',
            chr(197).chr(188) => 'z', chr(197).chr(189) => 'Z',
            chr(197).chr(190) => 'z', chr(197).chr(191) => 's'
        );

        $string = strtr($string, $chars);

        return trim($string);
    }
}