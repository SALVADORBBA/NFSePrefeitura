<?php
namespace NFSePrefeitura\NFSe;

class NFSeSender
{
    private $wsdl;
    private $certPath;
    private $certPassword;

    public function __construct($wsdl, $certPath, $certPassword)
    {
        $this->wsdl = $wsdl;
        $this->certPath = $certPath;
        $this->certPassword = $certPassword;
    }

    public function send($xmlAssinado, $method = 'RecepcionarLoteRps')
    {
        // Configuração do contexto SSL
        $options = [
            'local_cert' => $this->certPath,
            'passphrase' => $this->certPassword,
            'trace' => 1,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'soap_version' => SOAP_1_1,
            'encoding' => 'UTF-8'
        ];

        $client = new \SoapClient($this->wsdl, $options);

        // Monta o parâmetro conforme o método do ABRASF
        $params = [
            'xml' => $xmlAssinado
        ];

        // Chama o método do WebService
        $response = $client->__soapCall($method, [$params]);

        return $response;
    }
}
