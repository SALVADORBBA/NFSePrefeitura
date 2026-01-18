<?php
namespace NFSePrefeitura\NFSe;

use NFePHP\Common\Certificate;
use NFePHP\Common\Strings;
use NFePHP\Common\Signer;
use DOMDocument;
use InvalidArgumentException;

class NFSeNacionalSigner
{
    private $certificate;
    private $algorithm = OPENSSL_ALGO_SHA1;
    private $canonical = [false, false, null, null];

    public function __construct(Certificate $certificate)
    {
        $this->certificate = $certificate;
    }

    public static function assinarDpsXml($xml, string $certPath, string $certPassword, array $config = [])
    {
        if (!file_exists($certPath)) {
            throw new InvalidArgumentException('Certificado não encontrado: ' . $certPath);
        }

        $tools = new self(
            Certificate::readPfx(file_get_contents($certPath), $certPassword)
        );

        $response_assinado = $tools->signNFSeX($xml);

        return $response_assinado;
    }

    public function signNFSeX(string $xml): string 
    { 
        if (empty($xml)) { 
            throw new InvalidArgumentException('O argumento xml passado para ser assinado está vazio.'); 
        } 
        
        $xml = Strings::clearXmlString($xml); 
        
        $signed = Signer::sign( 
            $this->certificate, 
            $xml, 
            'infDPS', 
            'Id', 
            $this->algorithm, 
            $this->canonical 
        ); 
        
        $dom = new DOMDocument('1.0', 'UTF-8'); 
        $dom->preserveWhiteSpace = false; 
        $dom->formatOutput = false; 
        $dom->loadXML($xml); 

        return $signed; 
    }
}