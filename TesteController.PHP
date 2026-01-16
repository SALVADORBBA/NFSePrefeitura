<?php

use DevelopApi\PortoSeguro;

class TesteController extends TPage
{
    public function __construct($param)
    {
        parent::__construct();
    }

    public function onShow($param = null)
    {
        try {
 
            $dados = [
                'prestador' => [
                    'cnpjPrestador'       => '49535940000174',
                    'inscricao_municipal' => '173013001',
                    'razao_social'        => 'QM PISCINAS LTDA',
                    'endereco'            => 'Rua das Flores, 100',
                    'bairro'              => 'Centro',
                    'cep'                 => '48970000',
                    'cidade'              => 'Porto Seguro',
                    'uf'                  => 'BA',
                    'email'               => 'contato@empresa.com.br',
                ],
                'tomador' => [
                    'identificacao' => [
                'CpfCnpj' => '57219214553'
            ],
                    'nome'     => 'Joao da Silva',
                    'endereco' => 'Rua Teste, 123',
                    'bairro'   => 'Centro',
                    'cep'      => '48970000',
                    'cidade'   => 'Porto Seguro',
                    'uf'       => 'BA',
                    'email'    => 'joao@teste.com.br',
                ],
                'servico' => [
                    'descricao'      => 'Servico de teste',
                    'aliquota'       => 0.02, // 2% em decimal
                    'valor_servico'  => 100.00,
                    'codigo_servico' => '101',
                ],
                'rps' => [
                    'numero'       => '1',
                    'serie'        => 'A',
                    'tipo'         => 1,
                    // alguns schemas exigem datetime ISO: date('Y-m-d\TH:i:s')
                    'data_emissao' => date('Y-m-d\TH:i:s'), // Formato ISO 8601
                ],
            ];
 
            // =========================================
            $certPath = realpath('app/certificado/1/6968c3b3791e6_695fa57fadf87_quimica.pfx');
            $certPass = 'baby7902';

            // Se seu provedor NÃO exige certificado no SOAP, pode comentar essas 2 linhas:
            $this->assertFile($certPath, 'Certificado não encontrado');

    
            $params = PortoSeguro::enviarLoteRps([
                'numeroLote' => '1',
                'cnpjPrestador' => $dados['prestador']['cnpjPrestador'],
                'inscricaoMunicipal' => $dados['prestador']['inscricao_municipal'],
                'quantidadeRps' => 1,
                'rps' => [[
                    'infRps' => [
                        'numero' => $dados['rps']['numero'],
                        'serie'  => $dados['rps']['serie'],
                        'tipo'   => $dados['rps']['tipo'],
                        'dataEmissao' => $dados['rps']['data_emissao'],
                    ],
                    'discriminacao' => $dados['servico']['descricao'],
                    'itemListaServico' => $dados['servico']['codigo_servico'],
                    'tomador' => [
                    'IdentificacaoTomador' => [
                        'CpfCnpj' => $dados['tomador']['cpf_cnpj']
                    ],
                        'razaoSocial' => $dados['tomador']['nome'],
                        'endereco' => [
                            'logradouro' => $dados['tomador']['endereco'],
                            'numero' => '',
                            'bairro' => $dados['tomador']['bairro'],
                            'codigoMunicipio' => '2925303',
                            'uf' => $dados['tomador']['uf'],
                            'cep' => $dados['tomador']['cep'],
                        ]
                    ]
                ]]
            ]);

            // Convert SOAP response object to array if needed
            $paramsArray = (array)$params;
            $this->printBlock('nfseCabecMsg', $paramsArray['nfseCabecMsg'] ?? '');
            $this->printBlock('nfseDadosMsg', $paramsArray['nfseDadosMsg'] ?? '');

            // =========================
            // 4) SOAP Client (WSDL remoto)
            // =========================
            // First try to download WSDL locally as fallback
            $wsdlUrl = 'https://portoseguroba.gestaoiss.com.br/ws/nfse.asmx';
            $localWsdlPath = sys_get_temp_dir().'/nfse_wsdl.xml';
            
            if (!file_exists($localWsdlPath) || (time() - filemtime($localWsdlPath) > 3600)) {
                $context = stream_context_create(['http' => ['ignore_errors' => true]]);
                $wsdlContent = file_get_contents($wsdlUrl, false, $context);
                
                if ($wsdlContent === false || !simplexml_load_string($wsdlContent)) {
                    throw new Exception("Falha ao baixar WSDL: " . ($http_response_header[0] ?? 'Erro desconhecido'));
                }
                file_put_contents($localWsdlPath, $wsdlContent);
            }
            
            $wsdl = $wsdlUrl; // Usar sempre o WSDL remoto validado

            $options = [
                'trace'        => 1,
                'exceptions'   => true,
                'cache_wsdl'   => WSDL_CACHE_NONE,
                'soap_version' => SOAP_1_1,
                
                // Proxy settings if needed
                'proxy_host' => '',
                'proxy_port' => '',
                
                // Certificate authentication
                'local_cert' => $certPath,
                'passphrase' => $certPass,

                // SSL/TLS configuration
                'stream_context' => stream_context_create([
                    'ssl' => [
                        'verify_peer'      => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                        'crypto_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT,
                    ],
                    'http' => [
                        'timeout' => 30,
                        'user_agent' => 'PHP-SOAP'
                    ]
                ]),
                
                // Additional WSDL loading options
                'connection_timeout' => 30,
            ];

            $client = new SoapClient(PortoSeguro::getWsdlWithValidation(), $options);

            // Endpoint (nem sempre vem, mas quando vem ajuda)
            $location = method_exists($client, '__getLocation') ? $client->__getLocation() : '';
            if ($location) {
                $this->printBlock('SOAP Location', $location);
            }
 
            $method = 'GerarNfse';

            $soapParams = [
    'nfseCabecMsg' => PortoSeguro::generateNfseCabecMsg($dados['prestador']),
    'nfseDadosMsg' => PortoSeguro::generateNfseDadosMsg($dados)
];
$response = $client->__soapCall($method, [$soapParams]);
            
            // Convert SOAP response to array for proper handling
            $responseArray = (array)$response;
            $this->printBlock('Resposta do Webservice', print_r($responseArray, true));
            $this->printBlock('SOAP Last Request', $client->__getLastRequest());
            $this->printBlock('SOAP Last Response', $client->__getLastResponse());

        } catch (SoapFault $sf) {

            $msg  = "Erro SOAP: " . $sf->getMessage();
            $msg .= "\nFaultCode: " . ($sf->faultcode ?? '-');
            $msg .= "\nFaultString: " . ($sf->faultstring ?? '-');

            $this->printBlock('Falha SOAP', $msg);

        } catch (Exception $e) {

            $this->printBlock('Erro geral', $e->getMessage());
        }
    }
  

    private function assertFile($path, $message)
    {
        if (!$path || !file_exists($path)) {
            throw new Exception($message . ' => ' . ($path ?: '(path vazio)'));
        }
    }

    private function printBlock(string $title, string $content): void
    {
        echo "<div style='margin:10px 0; padding:10px; border:1px solid #ddd; border-radius:6px;'>";
        echo "<div style='font-weight:bold; margin-bottom:6px;'>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</div>";
        echo "<pre style='white-space:pre-wrap; margin:0;'>" . htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</pre>";
        echo "</div>";
    }
}