<?php


namespace Services\Sumsub;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use function GuzzleHttp\Psr7\stream_for;

define('SUMSUB_SECRET_KEY', config('settings.sumsub_secret'));
define('SUMSUB_APP_TOKEN', config('settings.sumsub_token'));
define('SUMSUB_BASE_URL', config('settings.sumsub_base'));

class Api
{
    /**
     * https://developers.sumsub.com/api-reference/#creating-an-applicant
     * @param string $externalUserId
     * @param array $requiredIdDocs
     * @param string $lang
     * @return string
     * @throws GuzzleException
     */
    public function createDirector(array $attributes, $levelName)
    {
        $requestBody = [
            'externalUserId' => $attributes['id'],
            'email' => $attributes['email'],
            'phone' => $attributes['phone'],
            'fixedInfo' => [
                'firstName' => $attributes['firstName'],
                'lastName' => $attributes['lastName']
            ],
            'type' => 'individual'
        ];

        $url = '/resources/applicants?levelName=' . $levelName;
        $request = new \GuzzleHttp\Psr7\Request('POST', SUMSUB_BASE_URL . $url);
        $request = $request->withHeader('Content-Type', 'application/json');
        $request = $request->withBody(\GuzzleHttp\Psr7\Utils::streamFor(json_encode($requestBody)));
        $responseBody = $this->sendHttpRequest($request, $url)->getBody();
        return json_decode($responseBody)->{'id'};
    }

    public function createApplicant(array $attributes, $levelName)
    {
        $requestBody = [
            'externalUserId' => $attributes['id'],
            'email' => $attributes['email'],
            'phone' => $attributes['phone'],
            'fixedInfo' => [
                'firstName' => $attributes['firstName'],
                'lastName' => $attributes['lastName'],
                'dob' => $attributes['dob'],
                'country' => $attributes['country'],
                'addresses' => array(
                    array(
                        "country" => $attributes['country'],
                        "town" => $attributes['city'],
                        "street" => $attributes['street'],
                        "state" => $attributes['state'],
                        "postCode" => $attributes['postCode'],
                    )
                )

            ]
        ];

        if($attributes['country'] == "NGA"){
            $requestBody['fixedInfo'] = array_merge(['tin' => $attributes['tin']], $requestBody['fixedInfo']);
        }else if($attributes['country'] == "USA"){
            $requestBody['fixedInfo'] = array_merge(['tin' => $attributes['tin']], $requestBody['fixedInfo']);
        }

        $url = '/resources/applicants?levelName=' . $levelName;
        $request = new \GuzzleHttp\Psr7\Request('POST', SUMSUB_BASE_URL . $url);
        $request = $request->withHeader('Content-Type', 'application/json');
        $request = $request->withBody(\GuzzleHttp\Psr7\Utils::streamFor(json_encode($requestBody)));
        $responseBody = $this->sendHttpRequest($request, $url)->getBody();
        return json_decode($responseBody)->{'id'};
    }

    public function updateCompanyInfo(array $attributes, string $applicantId)
    {
        $requestBody = $attributes;

        $url = '/resources/applicants/'.$applicantId.'/info/companyInfo';
        $request = new \GuzzleHttp\Psr7\Request('PATCH', SUMSUB_BASE_URL . $url);
        $request = $request->withHeader('Content-Type', 'application/json');
        $request = $request->withBody(\GuzzleHttp\Psr7\Utils::streamFor(json_encode($requestBody)));
        $responseBody = $this->sendHttpRequest($request, $url)->getBody();
        return json_decode($responseBody);
    }

    public function getCompanyData(string $applicantId): array
    {
        //https://developers.sumsub.com/api-reference/#getting-applicant-data
        $url = '/resources/checks/latest?applicantId='.$applicantId.'&type=COMPANY';
        $request = new Request('GET', SUMSUB_BASE_URL . $url);
        $response = $this->sendHttpRequest($request, $url)->getBody();
        return json_decode($response, true);
    }

    /**
     * @param RequestInterface $request
     * @param $url
     * @return ResponseInterface
     * @throws GuzzleException
     */
    public function sendHttpRequest(RequestInterface $request, $url): ResponseInterface
    {
        $client = new Client();
        $ts = round(time());

        $request = $request->withHeader('X-App-Token', SUMSUB_APP_TOKEN);
        $request = $request->withHeader('X-App-Access-Sig', $this->createSignature($ts, $request->getMethod(), $url, $request->getBody()));
        $request = $request->withHeader('X-App-Access-Ts', $ts);

        return $client->send($request);
    }

    private function createSignature($ts, $httpMethod, $url, $httpBody): string
    {
        return hash_hmac('sha256', $ts . strtoupper($httpMethod) . $url . $httpBody, SUMSUB_SECRET_KEY);
    }

    /**
     * @param string $file
     * @param string $applicantId
     * @return string
     * @throws GuzzleException
     */

    public function checkApplicant(string $applicantId, string $reason = null)
    {
        if ($reason == null) {
            $url = "/resources/applicants/$applicantId/status/pending";
        } else {
            $url = "/resources/applicants/$applicantId/status/pending?reason=$reason";
        }
        $request = new \GuzzleHttp\Psr7\Request('POST', SUMSUB_BASE_URL . $url);
        $response = $this->sendHttpRequest($request, $url);
        return json_decode($response->getBody(), true);
    }

    public function resetVerification($applicantId, $type): array
    {
        // https://developers.sumsub.com/api-reference/#getting-applicant-status-api
        $url = "/resources/applicants/$applicantId/resetStep/$type";

        $request = new \GuzzleHttp\Psr7\Request('POST', SUMSUB_BASE_URL . $url);
        $response = $this->sendHttpRequest($request, $url);
        return json_decode($response->getBody(), true);
    }

    public function resetApplicant($applicantId): array
    {
        // https://developers.sumsub.com/api-reference/#getting-applicant-status-api
        $url = "/resources/applicants/$applicantId/reset";

        $request = new \GuzzleHttp\Psr7\Request('POST', SUMSUB_BASE_URL . $url);
        $response = $this->sendHttpRequest($request, $url);
        return json_decode($response->getBody(), true);
    }

    public function addDocument(string $applicantId, array $attributes)
    {
        $file = $attributes['path'];

        $multipart = new MultipartStream([
            [
                "name" => "metadata",
                "contents" => json_encode($attributes)
            ],
            [
                'name' => 'content',
                'contents' => fopen($file, 'r')
            ],
        ]);
        $url = "/resources/applicants/" . $applicantId . "/info/idDoc";
        $request = new \GuzzleHttp\Psr7\Request('POST', SUMSUB_BASE_URL . $url);
        $request = $request->withHeader('X-Return-Doc-Warnings', true);
        $request = $request->withBody($multipart);
        $responseBody = $this->sendHttpRequest($request, $url);
        $body = $responseBody->getBody()->getContents();
        return [
            'header' => $responseBody->getHeader("X-Image-Id")[0],
            'body' => $body,
        ];
    }

    /**
     * @param $applicantId
     * @return array
     * @throws GuzzleException
     */
    public function getApplicantStatus($applicantId): array
    {
        // https://developers.sumsub.com/api-reference/#getting-applicant-status-api
        $url = "/resources/applicants/" . $applicantId . "/requiredIdDocsStatus";
        $request = new Request('GET', SUMSUB_BASE_URL . $url);

        $stream = $this->sendHttpRequest($request, $url)->getBody();
        return json_decode($stream, true);
    }


    /**
     * @param string $applicantId
     * @return StreamInterface
     * @throws GuzzleException
     */
    public function getApplicantStatusSDK(string $applicantId): StreamInterface
    {
        // https://developers.sumsub.com/api-reference/#getting-applicant-status-sdk
        $url = "/resources/applicants/" . $applicantId . "/status";
        $request = new Request('GET', SUMSUB_BASE_URL . $url);

        return $this->sendHttpRequest($request, $url)->getBody();
    }

    /**
     * @param string $userId
     * @return string
     * @throws GuzzleException
     */
    public function getAccessToken(string $userId, string $levelName): array
    {
        // https://developers.sumsub.com/api-reference/#access-tokens-for-sdks
        $url = "/resources/accessTokens?userId=" . $userId . "&levelName=" . $levelName;
        $request = new Request('POST', SUMSUB_BASE_URL . $url);
        $response = $this->sendHttpRequest($request, $url)->getBody();
        return json_decode($response, true);
    }

    /**
     * @param string $applicantId
     * @return array
     * @throws GuzzleException
     */
    public function getApplicantDataByApplicantId(string $applicantId): array
    {
        //https://developers.sumsub.com/api-reference/#getting-applicant-data
        $url = "/resources/applicants/" . $applicantId . "/one";
        $request = new Request('GET', SUMSUB_BASE_URL . $url);
        $response = $this->sendHttpRequest($request, $url)->getBody();
        return json_decode($response, true);
    }

    /**
     * @param string $userId
     * @return array
     * @throws GuzzleException
     */
    public function getApplicantDataByUserId(string $userId): array
    {

        //https://developers.sumsub.com/api-reference/#getting-applicant-data
        $url = "/resources/applicants/-;externalUserId=" . $userId . "/one";

        $request = new Request('GET', SUMSUB_BASE_URL . $url);
        $response = $this->sendHttpRequest($request, $url)->getBody();

        return json_decode($response, true);
    }

    /**
     * @param string $inspectionId
     * @param string $imageId
     * @return array
     * @throws GuzzleException
     */
    public function getDocumentImage(string $inspectionId, string $imageId): array
    {
        //https://developers.sumsub.com/api-reference/#getting-document-images
        $url = "/resources/inspections/$inspectionId/resources/$imageId";
        $request = new Request('GET', SUMSUB_BASE_URL . $url);
        $response = $this->sendHttpRequest($request, $url);
        return [
            'content' => $response->getBody(),
            'mime-type' => $response->getHeader('Content-Type')
        ];
    }

    /**
     * @param string $applicantId
     * @param string $inspectionId
     * @return array
     * @throws GuzzleException
     */
    public function getApplicantDocImages(string $applicantId, string $inspectionId): array
    {
        $images = [];
        $requiredIdDocsStatus = $this->getApplicantStatus($applicantId);
        foreach ($requiredIdDocsStatus as $doc) {
            foreach ($doc['imageIds'] as $imageId) {
                $images[$imageId] = [
                    'idDocType' => $doc['idDocType'],
                    //'image' => $this->getDocumentImage($inspectionId, $imageId)
                ];
            }
        }
        return $images;
    }


    public function getVerificationLink(string $userId, string $levelName, int $expiryTime)
    {
        $url = "/resources/sdkIntegrations/levels/$levelName/websdkLink?ttlInSecs=$expiryTime&externalUserId=$userId";
        $request = new Request('POST', SUMSUB_BASE_URL . $url);
        $response = $this->sendHttpRequest($request, $url)->getBody();
        return json_decode($response, true);
    }
}

