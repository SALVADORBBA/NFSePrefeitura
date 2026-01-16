<?php 

namespace NFSePrefeitura;

use NFePHP\Common\Certificate;
use NFePHP\Common\Signer;
use NFePHP\Common\Strings;
use InvalidArgumentException;

class NFSeSigner
{
    public static function sign($xml, $certPath, $certPassword, $tag = 'InfDeclaracaoPrestacaoServico')
    {
        if (empty($xml)) {
            throw new InvalidArgumentException('O XML passado para assinatura está vazio.');
        }
        if (!file_exists($certPath)) {
            throw new InvalidArgumentException('Certificado não encontrado: ' . $certPath);
        }
        $certificate = Certificate::readPfx(file_get_contents($certPath), $certPassword);
        $xml = Strings::clearXmlString($xml);
        $algorithm = OPENSSL_ALGO_SHA1;
        $canonical = [false, false, null, null];
        $signedXml = Signer::sign(
            $certificate,
            $xml,
            $tag,
            'Id',
            $algorithm,
            $canonical
        );
        return $signedXml;
    }
}