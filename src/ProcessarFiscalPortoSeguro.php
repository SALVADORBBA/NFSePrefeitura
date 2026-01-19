<?php

use NFSePrefeitura\NFSe\PortoSeguro;
use NFSePrefeitura\NFSe\PortoSeguroSigner;

class ProcessarFiscalPortoSeguro {
    private $jsonData;
    private $nfseService;

    public function __construct($jsonData) {
        $this->jsonData = $jsonData;
        $this->nfseService = new NfseService();
    }

    public function processar() {
        // Validar JSON
        $this->validarDados();
        
        // Processar cada RPS
        foreach ($this->jsonData['rps'] as $rps) {
            $this->processarRps($rps);
        }
        
        // Gerar XML conforme padrão Porto Seguro
        $xml = $this->gerarXml();
        
        // Assinar XML
        $xmlAssinado = $this->assinarXml($xml);
        
        // Enviar para webservice
        return $this->enviarParaWebservice($xmlAssinado);
    }

    private function validarDados() {
        if (empty($this->jsonData['lote_id'])) {
            throw new \Exception("lote_id é obrigatório no JSON");
        }
        if (empty($this->jsonData['cnpjPrestador'])) {
            throw new \Exception("cnpjPrestador é obrigatório no JSON");
        }
        if (empty($this->jsonData['inscricaoMunicipal'])) {
            throw new \Exception("inscricaoMunicipal é obrigatório no JSON");
        }
        if (empty($this->jsonData['rps']) || !is_array($this->jsonData['rps'])) {
            throw new \Exception("Lista de RPS é obrigatória no JSON");
        }
        
        foreach ($this->jsonData['rps'] as $rps) {
            if (empty($rps['inf_id'])) {
                throw new \Exception("inf_id é obrigatório em cada RPS");
            }
            if (empty($rps['infRps']) || !is_array($rps['infRps'])) {
                throw new \Exception("infRps é obrigatório em cada RPS");
            }
            if (empty($rps['tomador']) || !is_array($rps['tomador'])) {
                throw new \Exception("tomador é obrigatório em cada RPS");
            }
        }
    }

    private function processarRps($rps) {
        // Garantir que os campos numéricos estão no formato correto
        if (isset($rps['valorServicos'])) {
            $rps['valorServicos'] = (float)$rps['valorServicos'];
        }
        if (isset($rps['valorIss'])) {
            $rps['valorIss'] = (float)$rps['valorIss'];
        }
        if (isset($rps['aliquota'])) {
            $rps['aliquota'] = (float)$rps['aliquota'];
        }
        
        // Garantir que campos obrigatórios do tomador existem
        if (empty($rps['tomador']['cpfCnpj'])) {
            throw new \Exception("cpfCnpj do tomador é obrigatório");
        }
        if (empty($rps['tomador']['razaoSocial'])) {
            throw new \Exception("razaoSocial do tomador é obrigatório");
        }
        if (empty($rps['tomador']['endereco']) || !is_array($rps['tomador']['endereco'])) {
            throw new \Exception("endereco do tomador é obrigatório");
        }
        
        return $rps;
    }

    private function gerarXml() {
        // Preparar dados para gerar XML conforme padrão PortoSeguro
        $dadosLote = [
            'lote_id' => $this->jsonData['lote_id'],
            'numeroLote' => $this->jsonData['numeroLote'],
            'cnpjPrestador' => $this->jsonData['cnpjPrestador'],
            'inscricaoMunicipal' => $this->jsonData['inscricaoMunicipal'],
            'quantidadeRps' => $this->jsonData['quantidadeRps'],
            'rps' => []
        ];

        // Processar cada RPS do JSON
        foreach ($this->jsonData['rps'] as $rpsJson) {
            $dadosLote['rps'][] = [
                'inf_id' => $rpsJson['inf_id'],
                'infRps' => $rpsJson['infRps'],
                'competencia' => $rpsJson['competencia'],
                'valorServicos' => $rpsJson['valorServicos'],
                'valorIss' => $rpsJson['valorIss'],
                'aliquota' => $rpsJson['aliquota'],
                'issRetido' => $rpsJson['issRetido'],
                'itemListaServico' => $rpsJson['itemListaServico'],
                'discriminacao' => $rpsJson['discriminacao'],
                'codigoMunicipio' => $rpsJson['codigoMunicipio'],
                'exigibilidadeISS' => $rpsJson['exigibilidadeISS'],
                'regimeEspecialTributacao' => $rpsJson['regimeEspecialTributacao'],
                'optanteSimplesNacional' => $rpsJson['optanteSimplesNacional'],
                'incentivoFiscal' => $rpsJson['incentivoFiscal'],
                'codigoCnae' => $rpsJson['codigoCnae'],
                'codigoTributacaoMunicipio' => $rpsJson['codigoTributacaoMunicipio'],
                'municipioIncidencia' => $rpsJson['municipioIncidencia'],
                'tomador' => $rpsJson['tomador']
            ];
        }

        // Usar a classe PortoSeguro para gerar o XML
        $portoSeguro = new PortoSeguro($this->nfseService->certPath, $this->nfseService->certPassword, $this->nfseService->wsdl);
        return $portoSeguro->gerarXmlLoteRps($dadosLote, '2.02');
    }

    private function assinarXml($xml) {
        try {
            // Criar instância do assinador com certificado e senha
            $assinador = new PortoSeguroSigner(
                $this->nfseService->certPath, 
                $this->nfseService->certPassword,
                $this->nfseService->wsdl
            );
            
            // Assinar o XML usando o método correto
            $xmlAssinado = $assinador->sign($xml);
            
            // Salvar XML assinado para debug
            $this->salvarXml("02_assinado.xml", $xmlAssinado);
            
            return $xmlAssinado;
        } catch (\Exception $e) {
            throw new \Exception("Erro ao assinar XML: " . $e->getMessage());
        }
    }

    private function enviarParaWebservice($xml) {
        try {
            // Salvar XML assinado para debug
            $this->salvarXml("02_assinado.xml", $xml);
            
            // Enviar para webservice usando o método 'RecepcionarLoteRps'
            $resposta = $this->nfseService->enviar($xml, 'RecepcionarLoteRps', '2.02');
            
            // Salvar resposta para debug
            if (isset($resposta->outputXML)) {
                $this->salvarXml("03_resposta.xml", $resposta->outputXML);
            } else {
                $this->salvarXml("03_resposta_dump.txt", print_r($resposta, true));
            }
            
            return $resposta;
        } catch (\Exception $e) {
            throw new \Exception("Erro ao enviar lote para webservice: " . $e->getMessage());
        }
    }
    
    private function salvarXml($nomeArquivo, $conteudo) {
        $caminho = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $nomeArquivo;
        file_put_contents($caminho, $conteudo);
        return $caminho;
    }
}