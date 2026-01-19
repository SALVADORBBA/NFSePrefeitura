<?php

namespace NFSePrefeitura\NFSe;

use NFSePrefeitura\NFSe\PortoSeguro;
use NFSePrefeitura\NFSe\AssinadorXMLSeguro;
use NFSePrefeitura\NFSe\Exceptions\NfseProcessingException;

class ProcessarFiscalPortoSeguro {
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
        $requiredFields = [
            'lote_id',
            'cnpjPrestador',
            'inscricaoMunicipal',
            'rps'
        ];

        foreach ($requiredFields as $field) {
            if (empty($this->jsonData[$field])) {
                throw new NfseProcessingException("Field {$field} is required in JSON data");
            }
        }

        if (!is_array($this->jsonData['rps'])) {
            throw new NfseProcessingException("RPS list must be an array");
        }

        foreach ($this->jsonData['rps'] as $index => $rps) {
            $this->validateRps($rps, $index);
        }
    }
    
    private function validateRps(array $rps, int $index): void
    {
        $requiredFields = [
            'inf_id',
            'infRps',
            'tomador',
            'discriminacao'
        ];

        foreach ($requiredFields as $field) {
            if (empty($rps[$field])) {
                throw new NfseProcessingException("Field {$field} is required in RPS at index {$index}");
            }
        }

        if (!is_array($rps['infRps'])) {
            throw new NfseProcessingException("infRps must be an array in RPS at index {$index}");
        }

        if (!is_array($rps['tomador'])) {
            throw new NfseProcessingException("tomador must be an array in RPS at index {$index}");
        }

        $this->validateTomador($rps['tomador'], $index);
    }
    
    private function validateTomador(array $tomador, int $rpsIndex): void
    {
        $requiredFields = [
            'cpfCnpj',
            'razaoSocial',
            'endereco'
        ];

        foreach ($requiredFields as $field) {
            if (empty($tomador[$field])) {
                throw new NfseProcessingException("Field tomador.{$field} is required in RPS at index {$rpsIndex}");
            }
        }

        if (!is_array($tomador['endereco'])) {
            throw new NfseProcessingException("tomador.endereco must be an array in RPS at index {$rpsIndex}");
        }

        $requiredAddressFields = [
            'logradouro',
            'numero',
            'bairro',
            'uf',
            'cep'
        ];

        foreach ($requiredAddressFields as $field) {
            if (empty($tomador['endereco'][$field])) {
                throw new NfseProcessingException("Field tomador.endereco.{$field} is required in RPS at index {$rpsIndex}");
            }
        }
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
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($path, $xml);
    }
    
    private function logResponseForDebugging($response): void
    {
        $filename = '03_resposta_' . date('YmdHis') . '.xml';
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
        
        $content = isset($response->outputXML) 
            ? $response->outputXML 
            : print_r($response, true);
            
        file_put_contents($path, $content);
    }
}