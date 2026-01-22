# Biblioteca NFSe ABRASF 2.02

Biblioteca PHP para emissão de Notas Fiscais de Serviço Eletrônicas (NFSe) conforme padrão ABRASF 2.02.

## Funcionalidades
- Geração de XML para Envio de Lotes RPS
- Comunicação SOAP com webservices de prefeituras
- Validação de certificados digitais
- Suporte aos principais eventos:
    - Envio de Lote RPS
    - Consulta de Situação de Lote
    - Cancelamento de NFSe

## Instalação
```bash
composer require nfseprefeitura/nfse
```

## Cidades Suportadas

- **Porto Seguro/BA**  
    Provedor: Gestão ISS  
    Endpoint: https://portoseguroba.gestaoiss.com.br/ws/nfse.asmx?WSDL

    **Funções principais utilizadas:**
    - `PortoSeguro::gerarXmlLoteRps()` - Gera o XML do lote RPS no padrão ABRASF
    - `NFSeSigner::sign()` - Assina digitalmente o XML
    - `NfseService::enviar()` - Envia o lote para o webservice
    - `ProcessarFiscalPortoSeguro::processar()` - Processo completo (validação, geração XML, assinatura e envio)

    **Exemplos disponíveis:**
    - [Exemplo básico](src/exemplo/Exemplo_portoseguro.php) - Demonstra uso direto das classes
    - [Exemplo completo com JSON](src/exemplo/ExemploProcessarFiscalPortoSeguro.php) - Processamento automatizado via JSON

- **Natal/RN** *(em desenvolvimento)*  
    Provedor: Betha Sistemas  
    Endpoint: https://natal.rn.gov.br/nfse/ws/nfse.asmx?WSDL
    [Exemplo de uso](src/exemplo/Exemplo_natal.php)

- **Chapecó/SC** *(em desenvolvimento)*  
    Provedor: Betha Sistemas  
    Endpoint: https://chapeco.sc.gov.br/nfse/ws/nfse.asmx?WSDL
    [Exemplo de uso](src/exemplo/Exemplo_chapeco.php)

## Sobre a MasterClass

A `MasterClass` centraliza funções utilitárias comuns e facilita o uso em qualquer parte do projeto. Ela oferece métodos para:

- **gerarNumeroRps**: Gera um número único para o RPS, usando série, número e CNPJ.
- **removerAcentos**: Remove acentos e caracteres especiais de textos, útil para padronizar campos.
- **MoedaNF**: Formata valores monetários para o padrão exigido pela NFSe (duas casas decimais, separador correto).
- **calcDescPercentual**: Calcula o percentual de desconto sobre um valor total.
- **descPercentual**: Calcula o percentual de lucro entre dois valores.
- **diferencaPercentual**: Calcula o percentual de diferença entre dois valores.
- **TrataDoc**: Normaliza documentos (CPF/CNPJ), removendo caracteres especiais.

### Exemplos de uso da MasterClass

```php
$master = new MasterClass();

// Gerar número RPS
$rps = $master->gerarNumeroRps('1', 123, '12345678000199');

// Remover acentos
$texto = $master->removerAcentos('João da Silva');

// Formatar moeda
$valorFormatado = $master->MoedaNF('1.234,56');

// Calcular percentual de desconto
$percentual = $master->calcDescPercentual(100, 10); // 10%

// Calcular percentual de lucro
$lucro = $master->descPercentual('100', '120'); // 20%

// Calcular percentual de diferença
$dif = $master->diferencaPercentual('200', '50'); // 25

// Normalizar documento
$doc = $master->TrataDoc('12.345.678/0001-99'); // 12345678000199
```

---

## Mudanças nas classes de Natal e Porto Seguro

- **Natal/RN:**
  Agora as classes relacionadas à prefeitura de Natal estão em fase de testes e refinamento. Os métodos estão sendo ajustados para garantir a correta emissão de NFSe e integração com o webservice da prefeitura. Recomenda-se usar a MasterClass para padronização de dados e cálculos durante o desenvolvimento.

- **Porto Seguro/BA:**
  As classes de Porto Seguro já estão em fase de emissão e produção, utilizando as funções utilitárias da MasterClass para garantir organização e padronização dos dados gerados.

---

## Recomendações

- Utilize a MasterClass para operações de padronização de texto, cálculos percentuais, formatação de moeda e normalização de documentos.
- Para novas cidades, siga o padrão de uso da MasterClass para facilitar manutenção e testes.
- Consulte os exemplos em `src/exemplo/` para ver como integrar as funções utilitárias no fluxo de emissão e testes.

---

## Documentação Oficial
- Manual ABRASF 2.02
- Exemplos XML
