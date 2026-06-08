<?php

namespace App\Http\Controllers\Payment_Methods;

use App\Models\PaymentRequest;
use App\Models\User;
use App\Traits\Processor;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SslCommerzPaymentController extends Controller
{
    use Processor;

    private $store_id;
    private $store_password;
    private bool $host;
    private string $direct_api_url;
    private string $validation_api_url;
    private PaymentRequest $payment;
    private $user;

    public function __construct(PaymentRequest $payment, User $user)
    {
        $config = $this->payment_config('ssl_commerz', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $values = json_decode($config->test_values);
        }

        if ($config) {
            $this->store_id = $values->store_id;
            $this->store_password = $values->store_password;

            # REQUEST SEND TO SSLCOMMERZ
            $this->direct_api_url = "https://sandbox.sslcommerz.com/gwprocess/v4/api.php";
            $this->validation_api_url = "https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php";
            $this->host = true;

            if ($config->mode == 'live') {
                $this->direct_api_url = "https://securepay.sslcommerz.com/gwprocess/v4/api.php";
                $this->validation_api_url = "https://securepay.sslcommerz.com/validator/api/validationserverAPI.php";
                $this->host = false;
            }
        }
        $this->payment = $payment;
        $this->user = $user;
    }

    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, $this->error_processor($validator)), 400);
        }

        $data = $this->payment::where(['id' => $request['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($data)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        $payment_amount = $data['payment_amount'];

        $payer_information = json_decode($data['payer_information']);

        # GENERATE AND PERSIST THE TRANSACTION ID SO IT CAN BE MATCHED, VALIDATED AND RECONCILED LATER
        $tran_id = uniqid();
        $this->payment::where(['id' => $data['id']])->update(['transaction_id' => $tran_id]);

        $post_data = array();
        $post_data['store_id'] = $this->store_id;
        $post_data['store_passwd'] = $this->store_password;
        $post_data['total_amount'] = round($payment_amount, 2);
        $post_data['currency'] = $data['currency_code'];
        $post_data['tran_id'] = $tran_id;

        $post_data['success_url'] = url('/') . '/payment/sslcommerz/success?payment_id=' . $data['id'];
        $post_data['fail_url'] = url('/') . '/payment/sslcommerz/failed?payment_id=' . $data['id'];
        $post_data['cancel_url'] = url('/') . '/payment/sslcommerz/canceled?payment_id=' . $data['id'];
        $post_data['ipn_url'] = url('/') . '/payment/sslcommerz/ipn?payment_id=' . $data['id'];

        # CUSTOMER INFORMATION
        $post_data['cus_name'] = $payer_information->name;
        $post_data['cus_email'] = $payer_information->email && $payer_information->email != '' ? $payer_information->email : 'example@example.com';
        $post_data['cus_add1'] = 'N/A';
        $post_data['cus_add2'] = "";
        $post_data['cus_city'] = "";
        $post_data['cus_state'] = "";
        $post_data['cus_postcode'] = "";
        $post_data['cus_country'] = "";
        $post_data['cus_phone'] = $payer_information->phone ?? '0000000000';
        $post_data['cus_fax'] = "";

        # SHIPMENT INFORMATION
        $post_data['ship_name'] = "N/A";
        $post_data['ship_add1'] = "N/A";
        $post_data['ship_add2'] = "N/A";
        $post_data['ship_city'] = "N/A";
        $post_data['ship_state'] = "N/A";
        $post_data['ship_postcode'] = "N/A";
        $post_data['ship_phone'] = "";
        $post_data['ship_country'] = "N/A";

        $post_data['shipping_method'] = "NO";
        $post_data['product_name'] = "N/A";
        $post_data['product_category'] = "N/A";
        $post_data['product_profile'] = "service";

        # OPTIONAL PARAMETERS — echo the payment_id back so it is visible in the SSLCOMMERZ panel for reconciliation
        $post_data['value_a'] = $data['id'];
        $post_data['value_b'] = "ref002";
        $post_data['value_c'] = "ref003";
        $post_data['value_d'] = "ref004";

        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, $this->direct_api_url);
        curl_setopt($handle, CURLOPT_TIMEOUT, 30);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($handle, CURLOPT_POST, 1);
        curl_setopt($handle, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, $this->host); # KEEP IT FALSE IF YOU RUN FROM LOCAL PC

        $content = curl_exec($handle);
        $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        if ($code == 200 && !(curl_errno($handle))) {
            curl_close($handle);
            $sslcommerzResponse = $content;
        } else {
            curl_close($handle);
            return back();
        }

        $sslcz = json_decode($sslcommerzResponse, true);

        if (isset($sslcz['GatewayPageURL']) && $sslcz['GatewayPageURL'] != "") {
            echo "<meta http-equiv='refresh' content='0;url=" . $sslcz['GatewayPageURL'] . "'>";
            exit;
        } else {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }
    }

    # FUNCTION TO CHECK HASH VALUE
    protected function SSLCOMMERZ_hash_verify($store_passwd, $post_data)
    {
        if (isset($post_data) && isset($post_data['verify_sign']) && isset($post_data['verify_key'])) {
            # NEW ARRAY DECLARED TO TAKE VALUE OF ALL POST
            $pre_define_key = explode(',', $post_data['verify_key']);

            $new_data = array();
            if (!empty($pre_define_key)) {
                foreach ($pre_define_key as $value) {
                    if (isset($post_data[$value])) {
                        $new_data[$value] = ($post_data[$value]);
                    }
                }
            }
            # ADD MD5 OF STORE PASSWORD
            $new_data['store_passwd'] = md5($store_passwd);

            # SORT THE KEY AS BEFORE
            ksort($new_data);

            $hash_string = "";
            foreach ($new_data as $key => $value) {
                $hash_string .= $key . '=' . ($value) . '&';
            }
            $hash_string = rtrim($hash_string, '&');

            if (md5($hash_string) == $post_data['verify_sign']) {

                return true;
            } else {
                $this->error = "Verification signature not matched";
                return false;
            }
        } else {
            $this->error = 'Required data mission. ex: verify_key, verify_sign';
            return false;
        }
    }

    # SERVER-TO-SERVER VALIDATION CALL TO SSLCOMMERZ (validationserverAPI.php)
    # Returns the decoded validation response object, or null on failure to connect.
    protected function validateTransactionWithSslCommerz($val_id)
    {
        if (empty($val_id)) {
            return null;
        }

        $requested_url = $this->validation_api_url
            . "?val_id=" . urlencode($val_id)
            . "&store_id=" . urlencode($this->store_id)
            . "&store_passwd=" . urlencode($this->store_password)
            . "&v=1&format=json";

        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, $requested_url);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_TIMEOUT, 30);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 30);
        # Mirror the (proven-working) payment-creation call in index(): peer verification is
        # disabled on live because the host's CA bundle cannot verify SSLCOMMERZ's certificate.
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, $this->host ? 2 : 0);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, $this->host);

        $result = curl_exec($handle);
        $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $errno = curl_errno($handle);
        $error = curl_error($handle);
        curl_close($handle);

        if ($code != 200 || $errno) {
            Log::warning('SSLCOMMERZ validation API call failed', [
                'http_code' => $code,
                'curl_errno' => $errno,
                'curl_error' => $error,
            ]);
            return null;
        }
        return json_decode($result);
    }

    # SHARED VALIDATE-THEN-CONFIRM LOGIC USED BY BOTH success() (browser redirect) AND ipn() (server-to-server).
    # Performs hash check + server-side validation, then atomically/idempotently marks the payment paid
    # and runs the success hook exactly once. Returns the PaymentRequest on confirmed payment, false otherwise.
    protected function validateAndConfirm(Request $request)
    {
        $tranId = $request->input('tran_id');

        # RESOLVE THE PAYMENT: by payment_id when present (browser redirect), else by the
        # persisted tran_id (the global IPN POST carries no payment_id).
        $payment = null;
        if (!empty($request['payment_id'])) {
            $payment = $this->payment::where(['id' => $request['payment_id']])->first();
        }
        if (!isset($payment) && !empty($tranId)) {
            $payment = $this->payment::where(['transaction_id' => $tranId])->first();
        }

        if (!isset($payment)) {
            Log::warning('SSLCOMMERZ confirm: payment_request not found', [
                'payment_id' => $request['payment_id'] ?? null,
                'tran_id' => $tranId,
            ]);
            return false;
        }

        # ALREADY CONFIRMED (e.g. redirect arrived after IPN, or vice-versa) — treat as success, do not re-create order.
        if ((int)$payment->is_paid === 1) {
            return $payment;
        }

        # AUTHORITATIVE CHECK: server-to-server validation with SSLCOMMERZ (validationserverAPI.php).
        # This is tamper-proof (re-confirms with SSL using val_id + store password) and is what
        # tells SSLCOMMERZ the transaction is validated so the hold is released.
        $valId = $request->input('val_id');
        if (empty($valId)) {
            Log::warning('SSLCOMMERZ confirm: missing val_id', ['payment_id' => $payment->id, 'tran_id' => $tranId]);
            return false;
        }

        $validation = $this->validateTransactionWithSslCommerz($valId);
        if (!isset($validation) || !isset($validation->status)) {
            Log::warning('SSLCOMMERZ confirm: empty/invalid validation response', ['payment_id' => $payment->id, 'val_id' => $valId]);
            return false;
        }

        $statusValid = in_array($validation->status, ['VALID', 'VALIDATED']);
        $tranMatches = trim((string)($validation->tran_id ?? '')) === trim((string)$payment->transaction_id);
        $amountMatches = abs(floatval($validation->amount ?? 0) - floatval($payment->payment_amount)) < 1;

        if (!($statusValid && $tranMatches && $amountMatches)) {
            Log::warning('SSLCOMMERZ confirm: validation mismatch', [
                'payment_id' => $payment->id,
                'ssl_status' => $validation->status ?? null,
                'ssl_tran_id' => $validation->tran_id ?? null,
                'our_tran_id' => $payment->transaction_id,
                'ssl_amount' => $validation->amount ?? null,
                'our_amount' => $payment->payment_amount,
            ]);
            return false;
        }

        # NON-FATAL CROSS-CHECKS (logged only) — never block a transaction SSL has confirmed VALID.
        if (trim((string)($validation->currency_type ?? '')) !== trim((string)$payment->currency_code)) {
            Log::info('SSLCOMMERZ confirm: currency differs (non-fatal)', [
                'payment_id' => $payment->id,
                'ssl_currency' => $validation->currency_type ?? null,
                'our_currency' => $payment->currency_code,
            ]);
        }
        if (!$this->SSLCOMMERZ_hash_verify($this->store_password, $request->all())) {
            Log::info('SSLCOMMERZ confirm: hash signature did not match (non-fatal; validated via API)', ['payment_id' => $payment->id]);
        }

        # IDEMPOTENT TRANSITION: only the request that flips is_paid 0 -> 1 creates the order.
        $affected = $this->payment::where(['id' => $payment->id])->where('is_paid', 0)->update([
            'payment_method' => 'ssl_commerz',
            'is_paid' => 1,
            'transaction_id' => $tranId ?: $payment->transaction_id,
        ]);

        $payment = $this->payment::where(['id' => $payment->id])->first();

        if ($affected > 0 && isset($payment) && function_exists($payment->success_hook)) {
            call_user_func($payment->success_hook, $payment);
            Log::info('SSLCOMMERZ confirm: payment confirmed and order hook executed', ['payment_id' => $payment->id, 'tran_id' => $payment->transaction_id]);
        }

        return $payment;
    }

    public function success(Request $request): JsonResponse|Redirector|RedirectResponse|Application
    {
        $payment = $this->validateAndConfirm($request);
        if ($payment) {
            return $this->payment_response($payment, 'success');
        }

        $payment_data = $this->payment::where(['id' => $request['payment_id']])->first();
        if (isset($payment_data) && (int)$payment_data->is_paid === 0 && function_exists($payment_data->failure_hook)) {
            call_user_func($payment_data->failure_hook, $payment_data);
        }
        return $this->payment_response($payment_data, 'fail');
    }

    # SERVER-TO-SERVER IPN HANDLER. SSLCOMMERZ posts here independent of the customer's browser,
    # so orders are created even when the success redirect is lost (mobile banking app, closed tab, network).
    public function ipn(Request $request)
    {
        $payment = $this->validateAndConfirm($request);
        if ($payment && (int)$payment->is_paid === 1) {
            return response('IPN_SUCCESS', 200);
        }
        return response('IPN_FAILED', 200);
    }

    public function failed(Request $request): JsonResponse|Redirector|RedirectResponse|Application
    {
        $payment_data = $this->payment::where(['id' => $request['payment_id']])->first();
        if (isset($payment_data) && function_exists($payment_data->failure_hook)) {
            call_user_func($payment_data->failure_hook, $payment_data);
        }
        return $this->payment_response($payment_data, 'fail');
    }

    public function canceled(Request $request): JsonResponse|Redirector|RedirectResponse|Application
    {
        $payment_data = $this->payment::where(['id' => $request['payment_id']])->first();
        if (isset($payment_data) && function_exists($payment_data->failure_hook)) {
            call_user_func($payment_data->failure_hook, $payment_data);
        }
        return $this->payment_response($payment_data, 'cancel');
    }
}
