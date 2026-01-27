# NFS-e Salvador - BA

Implementação do padrão ABRASF v2.04 para a Prefeitura de Salvador - Bahia.

## 📋 Estrutura

```
src/PFSalvador/
├── Salvador.php              # Classe principal para comunicação com o webservice
├── SalvadorGeradorXML.php    # Gerador de XML no padrão ABRASF
├── modelo_xml_salvador.xml   # Modelo de XML para referência
└── exemplos/
    └── ExemploSalvador.php   # Exemplos de uso
```

## 🚀 Instalação

```bash
composer require nfseprefeitura/nfse
```

## 📖 Uso Básico

### 1. Gerar XML de Lote RPS

```php
use NFSePrefeitura\NFSe\PFSalvador\SalvadorGeradorXML;

$gerador = new SalvadorGeradorXML();

$dadosLote = [
    'lote_id' => '12345',
    'numeroLote' => '2024000001',
    'cnpjPrestador' => '12345678000123',
    'inscricaoMunicipal' => '123456',
    'quantidadeRps' => '1',
    'rps' => [
        [
            'inf_id' => '123456789',
            'infRps' => [
                'numero' => '123456789',
                'serie' => '1',
                'tipo' => '1',
                'dataEmissao' => '2024-01-15T10:30:00',
            ],
            'competencia' => '2024-01-15',
            'valorServicos' => '1000.00',
            'valorIss' => '50.00',
            'baseCalculo' => '1000.00',
            'aliquota' => '0.05',
            'issRetido' => '1',
            'itemListaServico' => '14.01',
            'discriminacao' => 'Serviços de construção civil',
            'codigoMunicipio' => '2927408', // Salvador-BA
            // ... outros campos
        ],
    ],
];

$xml = $gerador->gerarXmlLoteRps($dadosLote);
```

### 2. Transmissão Completa

```php
use NFSePrefeitura\NFSe\PFSalvador\Salvador;

// Configurações
certPath = '/caminho/para/certificado.pfx';
certPassword = 'senha_do_certificado';
ambiente = 'homologacao'; // ou 'producao'

$salvador = new Salvador($certPath, $certPassword, $ambiente);

// Processo completo: gerar, assinar e transmitir
$resultado = $salvador->gerarAssinarTransmitirLoteRps($dadosLote);

echo "XML Gerado: " . $resultado['xml_gerado'];
echo "XML Assinado: " . $resultado['xml_assinado'];
echo "XML Resposta: " . $resultado['xml_resposta'];
```

### 3. Consultas

```php
// Consultar situação do lote
$resposta = $salvador->consultarSituacaoLoteRps(
    '12345678000123',    // CNPJ
    '123456',            // Inscrição Municipal
    '2024000001'         // Protocolo
);

// Consultar lote processado
$resposta = $salvador->consultarLoteRps(
    '12345678000123',    // CNPJ
    '123456',            // Inscrição Municipal
    '2024000001'         // Protocolo
);

// Cancelar NFSe
$resposta = $salvador->cancelarNfse(
    '12345678000123',    // CNPJ
    '123456',            // Inscrição Municipal
    '123456789',         // Número da NFSe
    '1',                 // Código do cancelamento
    'Erro de digitação'  // Justificativa
);
```

## 📊 Estrutura dos Dados

### Lote RPS

| Campo | Tipo | Descrição |
|-------|------|-----------|
| lote_id | string | ID único do lote |
| numeroLote | string | Número sequencial do lote |
| cnpjPrestador | string | CNPJ do prestador (14 dígitos) |
| inscricaoMunicipal | string | Inscrição municipal do prestador |
| quantidadeRps | int | Quantidade de RPS no lote |
| rps | array | Array com os dados dos RPS |

### RPS Individual

| Campo | Tipo | Descrição |
|-------|------|-----------|
| infRps.numero | string | Número do RPS |
| infRps.serie | string | Série do RPS |
| infRps.tipo | string | Tipo do RPS (1=RPS, 2=NF Conjugada, 3=Cupom) |
| infRps.dataEmissao | string | Data de emissão (ISO 8601) |
| competencia | string | Data da competência |
| valorServicos | decimal | Valor total dos serviços |
| valorIss | decimal | Valor do ISS |
| baseCalculo | decimal | Base de cálculo do ISS |
| aliquota | decimal | Alíquota do ISS (ex: 0.05 = 5%) |
| issRetido | string | ISS retido (1=Sim, 2=Não) |
| itemListaServico | string | Item da lista de serviços |
| discriminacao | string | Descrição dos serviços |
| codigoMunicipio | string | Código do município (2927408 = Salvador-BA) |

## 🔧 WebServices

### Ambiente de Homologação
- **URL WSDL**: `https://notahml.salvador.ba.gov.br/rps/ENVIOLOTERPS/EnvioLoteRPS.svc?wsdl` <mcreference link="https://notahml.salvador.ba.gov.br/rps/ENVIOLOTERPS/EnvioLoteRPS.svc?wsdl" index="1">1</mcreference>

### Ambiente de Produção
- **URL WSDL**: `https://nfse.salvador.ba.gov.br/rps/ENVIOLOTERPS/EnvioLoteRPS.svc?wsdl` <mcreference link="https://nfse.salvador.ba.gov.br/rps/ENVIOLOTERPS/EnvioLoteRPS.svc?wsdl" index="0">0</mcreference>

## 📄 Modelo XML

O arquivo `modelo_xml_salvador.xml` contém um exemplo completo de XML no padrão ABRASF v2.04 para Salvador-BA.

## 🔄 Padrão ABRASF

Esta implementação segue o padrão ABRASF v2.04, compatível com:
- Chapecó-SC
- Natal-RN
- Porto Seguro-BA
- Outras cidades que adotam o padrão ABRASF

## ⚠️ Observações Importantes

1. **Certificado Digital**: É necessário certificado digital A1 válido
2. **Inscrição Municipal**: O prestador deve estar cadastrado na prefeitura
3. **Alíquota**: Verificar a alíquota vigente em Salvador-BA
4. **Código do Município**: Salvador-BA = 2927408
5. **Homologação**: Sempre teste em ambiente de homologação antes da produção

## 📞 Suporte

Para dúvidas sobre:
- Regras de negócio específicas de Salvador-BA
- URLs dos webservices
- Alíquotas e tributações

Consulte:
- Site oficial da Prefeitura de Salvador
- Secretaria Municipal da Fazenda
- Documentação oficial da NFS-e de Salvador-BA

## 📝 Exemplos Adicionais

Veja o arquivo `exemplos/ExemploSalvador.php` para exemplos completos de:
- Geração de XML
- Transmissão de lote
- Consultas de situação
- Cancelamento de NFSe