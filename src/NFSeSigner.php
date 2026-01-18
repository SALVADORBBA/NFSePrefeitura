<?php 

namespace NFSePrefeitura\NFSe;

use NFePHP\Common\Certificate;
use NFePHP\Common\Signer;
use NFePHP\Common\Strings;
use InvalidArgumentException;
use SoapClient;
use SoapFault;

class NFSeSigner
{
    protected $certPath;
    protected $certPassword;
    protected $wsdl;

    public function __construct(string $certPath, string $certPassword, string $wsdl)
    {
        $this->certPath = $certPath;
        $this->certPassword = $certPassword;
        $this->wsdl = $wsdl;
    }

    public function generateAndSign(array $dados, string $tag = 'InfDeclaracaoPrestacaoServico'): string
    {
        $portoSeguro = new PortoSeguro($this->certPath, $this->certPassword);
        $xml = $portoSeguro->gerarXmlLoteRps($dados);
        return $this->sign($xml, $tag);
    }

    public function sign(string $xml, string $tag = 'InfDeclaracaoPrestacaoServico'): string
    {
        if (empty($xml)) {
            throw new InvalidArgumentException('O XML passado para assinatura está vazio.');
        }
        if (!file_exists($this->certPath)) {
            throw new InvalidArgumentException('Certificado não encontrado: ' . $this->certPath);
        }
        
        $certificate = Certificate::readPfx(file_get_contents($this->certPath), $this->certPassword);
        $xml = Strings::clearXmlString($xml);
        
        return Signer::sign(
            $certificate,
            $xml,
            $tag,
            'Id',
            OPENSSL_ALGO_SHA1,
            [false, false, null, null],
            'Signature'
        );
    }

    public function transmit(string $signedXml, string $versao = '2.02'): string
    {
        $client = new SoapClient($this->wsdl, [
            'soap_version' => SOAP_1_1,
            'trace' => true,
            'exceptions' => true,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'local_cert' => $this->certPath,
                    'passphrase' => $this->certPassword,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ])
        ]);

        $cabec = '<cabecalho xmlns="http://www.abrasf.org.br/nfse.xsd" versao="'.$versao.'">'
               . '<versaoDados>'.$versao.'</versaoDados>'
               . '</cabecalho>';

        try {
            $response = $client->__soapCall('RecepcionarLoteRps', [[
                'nfseCabecMsg' => $cabec,
                'nfseDadosMsg' => $signedXml
            ]]);
            return $response;
        } catch (SoapFault $e) {
            throw new SoapFault($e->getMessage());
        }
    }

    public static function quickSign($xml, $certPath, $certPassword, $tag = 'InfDeclaracaoPrestacaoServico')
    {
        $signer = new self($certPath, $certPassword, '');
        return $signer->sign($xml, $tag);
    }
}