<?php

namespace NFSePrefeitura\NFSe\PFPortoSeguro;

use NFSePrefeitura\NFSe\PFPortoSeguro\PortoSeguro;
use NFSePrefeitura\NFSe\PFPortoSeguro\AssinadorXMLSeguro;
use NFSePrefeitura\NFSe\PFPortoSeguro\Exceptions\NfseProcessingException;

class ProcessarFiscalPortoSeguro
{
    private array $jsonData;
    private string $certPath;
    private string $certPassword;
    private string $wsdlPath;
    private PortoSeguro $portoSeguro;
    private AssinadorXMLSeguro $signer;

    public function __construct(
        array $jsonData,
        string $certPath,
        string $certPassword,
        string $wsdlPath
    ) {
        $this->jsonData = $jsonData;
        $this->certPath = $certPath;
        $this->certPassword = $certPassword;
        $this->wsdlPath = $wsdlPath;

        $this->initializeDependencies();
    }

    public function processar(): array
    {
        try {
            $this->validateInputData();
            $processedRps = $this->processRpsList();
            $xml = $this->generateSignedXml();
            return $this->sendToWebservice($xml);
        } catch (NfseProcessingException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new NfseProcessingException('NFSe processing failed: ' . $e->getMessage(), 0, $e);
        }
    }

    private function validateInputData(): void
    {
        // Validação removida conforme solicitado
    }

    private function validateRps(array $rps, int $index): void
    {
        // Validação removida conforme solicitado
    }

    private function validateTomador(array $tomador, int $rpsIndex): void
    {
        // Validação removida conforme solicitado
    }

    private function normalizeFloat(float $value): float
    {
        return round($value, 2);
    }

    private function prepareServicoData(?array $servico): array
    {
        if ($servico === null) {
            return [
                'valorServicos' => 0.0,
          
           
    
        
     
                'aliquota' => 0.0,
    
                'discriminacao' => '',
                'codigoMunicipio' => ''
            ];
        }

        return [
            'valorServicos' => $this->normalizeFloat($servico['valorServicos'] ?? 0.0),
            'valorDeducoes' => $this->normalizeFloat($servico['valorDeducoes'] ?? 0.0),
            'valorPis' => $this->normalizeFloat($servico['valorPis'] ?? 0.0),
            'valorCofins' => $this->normalizeFloat($servico['valorCofins'] ?? 0.0),
            'valorInss' => $this->normalizeFloat($servico['valorInss'] ?? 0.0),
            'valorIr' => $this->normalizeFloat($servico['valorIr'] ?? 0.0),
            'valorCsll' => $this->normalizeFloat($servico['valorCsll'] ?? 0.0),
            'issRetido' => $servico['issRetido'] ?? false,
            'valorIss' => $this->normalizeFloat($servico['valorIss'] ?? 0.0),
            'valorIssRetido' => $this->normalizeFloat($servico['valorIssRetido'] ?? 0.0),
            'outrasRetencoes' => $this->normalizeFloat($servico['outrasRetencoes'] ?? 0.0),
            'baseCalculo' => $this->normalizeFloat($servico['baseCalculo'] ?? 0.0),
            'aliquota' => $this->normalizeFloat($servico['aliquota'] ?? 0.0),
            'valorLiquidoNfse' => $this->normalizeFloat($servico['valorLiquidoNfse'] ?? 0.0),
            'descontoIncondicionado' => $this->normalizeFloat($servico['descontoIncondicionado'] ?? 0.0),
            'descontoCondicionado' => $this->normalizeFloat($servico['descontoCondicionado'] ?? 0.0),
            'itemListaServico' => $servico['itemListaServico'] ?? '',
            'codigoTributacaoMunicipio' => $servico['codigoTributacaoMunicipio'] ?? '',
            'discriminacao' => $servico['discriminacao'] ?? '',
            'codigoMunicipio' => $servico['codigoMunicipio'] ?? ''
        ];
    }

    private function prepareRpsData(array $rps): array
    {
        // Se não houver objeto 'servico', cria um com os campos do serviço que estão no nível raiz
        $servicoData = isset($rps['servico']) ? $rps['servico'] : [
            'valorServicos' => $rps['valorServicos'],
            'valorIss' => $rps['valorIss'],
            'aliquota' => $rps['aliquota'],
            'issRetido' => $rps['issRetido'],
            'itemListaServico' => $rps['itemListaServico'],
            'discriminacao' => $rps['discriminacao'],
            'codigoMunicipio' => $rps['codigoMunicipio'],
            'codigoTributacaoMunicipio' => $rps['codigoTributacaoMunicipio']
        ];

        return [
            'inf_id' => $rps['inf_id'],
            'infRps' => $rps['infRps'],
            'numero' => $rps['numero'],
            'serie' => $rps['serie'],
            'tipo' => $rps['tipo'],
            'dataEmissao' => $rps['dataEmissao'],
            'naturezaOperacao' => $rps['naturezaOperacao'],
            'optanteSimplesNacional' => $rps['optanteSimplesNacional'],
            'incentivadorCultural' => $rps['incentivadorCultural'],
            'status' => $rps['status'],
            'competencia' => $rps['competencia'],
            'servico' => $this->prepareServicoData($servicoData),
            'prestador' => $rps['prestador'],
            'tomador' => $rps['tomador']
        ];
    }

    private function validateTomador(array $tomador, int $rpsIndex): void
    {
        // Validação removida conforme solicitado
    }

    private function processRpsList(): array
    {
        $processedRps = [];

        foreach ($this->jsonData['rps'] as $index => $rps) {
            $processedRps[$index] = $this->processSingleRps($rps);
        }

        return $processedRps;
    }

    private function processSingleRps(array $rps): array
    {
        $index = array_search($rps, $this->jsonData['rps'], true);
        $this->validateRps($rps, $index !== false ? $index : 0);

        return [
            'valorServicos' => $this->normalizeFloat($rps['valorServicos'] ?? 0),
            'valorIss' => $this->normalizeFloat($rps['valorIss'] ?? 0),
            'aliquota' => $this->normalizeFloat($rps['aliquota'] ?? 0),
            'tomador' => $this->validateTomador($rps['tomador'], $index !== false ? $index : 0)
        ];
    }

    private function generateSignedXml(): string
    {
        $loteData = $this->prepareLoteData();
        $xml = $this->portoSeguro->gerarXmlLoteRps($loteData, '2.02');
        return $this->signer->sign($xml);
    }

    private function prepareLoteData(): array
    {
        return [
            'lote_id' => $this->jsonData['lote_id'],
            'numeroLote' => $this->jsonData['numeroLote'],
            'cnpjPrestador' => $this->jsonData['cnpjPrestador'],
            'inscricaoMunicipal' => $this->jsonData['inscricaoMunicipal'],
            'quantidadeRps' => $this->jsonData['quantidadeRps'],
            'rps' => array_map([$this, 'prepareRpsData'], $this->jsonData['rps'])
        ];
    }

    private function initializeDependencies(): void
    {
        $this->portoSeguro = new PortoSeguro(
            $this->certPath,
            $this->certPassword,
            $this->wsdlPath
        );

        $this->signer = new AssinadorXMLSeguro(
            $this->certPath,
            $this->certPassword,
            $this->wsdlPath
        );
    }

    private function sendToWebservice(string $xml): array
    {
        $this->logXmlForDebugging($xml, '02_assinado.xml');

        $response = $this->portoSeguro->recepcionarLoteRps($xml);

        $this->logResponseForDebugging($response);

        return [
            'status' => 'success',
            'response' => $response
        ];
    }

    private function logXmlForDebugging(string $xml, string $filename): void
    {
        echo "XML Assinado:\n\n" . $xml . "\n\n";
    }

    private function logResponseForDebugging($response): void
    {
        $content = isset($response->outputXML) 
            ? $response->outputXML 
            : print_r($response, true);
        
        echo "Resposta do WebService:\n\n" . $content . "\n\n";
    }
}