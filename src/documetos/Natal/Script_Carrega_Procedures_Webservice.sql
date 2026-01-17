


GO
/****** Object:  StoredProcedure [Nfse].[sp_WebService_Recepcao]    Script Date: 12/13/2012 11:15:52 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO


/*
-- =====================================================================================================================
-- Autor:		Marcelo Augusto de Oliveira
-- Criação:		12/03/2012
-- Descrição:	Recepção do Webservice
-- =====================================================================================================================

MANUTENÇÕES (Data - Desenvolvedor - Atualização - Solicitante):

*/

CREATE    PROCEDURE [Nfse].[sp_WebService_Recepcao]
(
------------------------------------------------------------------------------------------------------------------------
-- PARAMETROS 
------------------------------------------------------------------------------------------------------------------------
	@xmlcabecalho				XML= NULL,
	@xmlentrada					VARCHAR(MAX)= NULL,
	@cnpjautenticacao			VARCHAR(14)	= NULL,
	@servico					INT = NULL,	
	@cnpjassinatura				VARCHAR(14)	= NULL,
	@codigoerro					VARCHAR(4)	= NULL	
	
)

AS
BEGIN 
	SET NOCOUNT ON		
	SET XACT_ABORT ON

DECLARE 
		@idusuario					INT,
		@idsessao					INT,
		@xmlvalido					XML(NfseNovo),
		@xmlparametro				XML,
		@xml						XML,
		@MsgErro					VARCHAR(255),
		@xmlretorno					XML,
		@idnotaretorno				INT,	
		@resposta					VARCHAR(50),
		@sql						VARCHAR(8000),
		@idxmlretorno				VARCHAR(31),
		@versaocabecalho			VARCHAR(4),
		@versaodados				VARCHAR(4)

DECLARE
		@listaerros					TABLE	(codigoerro		VARCHAR(4))	
	
------------------------------------------------------------------------------------------------------------------------
-- CRÍTICAS
------------------------------------------------------------------------------------------------------------------------

------------------------------------------------------------------------------------------------------------------------
--Verifica se rotina de assinatura gerou erro
------------------------------------------------------------------------------------------------------------------------
	IF @codigoerro IS NOT NULL
		INSERT INTO @listaerros (codigoerro) VALUES (@codigoerro)

------------------------------------------------------------------------------------------------------------------------
--N29 - Usuário não cadastrado no Sistema
------------------------------------------------------------------------------------------------------------------------		
	IF NOT EXISTS(SELECT usr_codigo FROM Fr_Usuario WHERE usr_login = @cnpjautenticacao)
		INSERT INTO @listaerros (codigoerro) VALUES ('N29')
	ELSE
	BEGIN
		SELECT @idusuario=usr_codigo FROM Fr_Usuario WHERE usr_login = @cnpjautenticacao
	
		------------------------------------------------------------------------------------------------------------------------
		-- ABRE SEÇÃO
		------------------------------------------------------------------------------------------------------------------------

		Exec Sis.sp_Sessao_webservice_ins @idusuario,'WebService','WebService',@idsessao OUTPUT
	END
------------------------------------------------------------------------------------------------------------------------
---N30 - Não foi possível criar a sessão
------------------------------------------------------------------------------------------------------------------------
	IF @idsessao IS NULL
		INSERT INTO @listaerros (codigoerro) VALUES ('N30')
	
------------------------------------------------------------------------------------------------------------------------
---E160 - Arquivo em desacordo com o XML Schema.
------------------------------------------------------------------------------------------------------------------------

	
	BEGIN TRY
		SET @xmlvalido = @xmlentrada				
	END TRY
	BEGIN CATCH
		    
       	INSERT INTO @listaerros (codigoerro) VALUES ('E160')
		
	END CATCH			


------------------------------------------------------------------------------------------------------------------------
-- Analisa o Cabeçalho do Webservice
------------------------------------------------------------------------------------------------------------------------

-- Recuperando informações do XML

	;WITH XMLNAMESPACES(DEFAULT 'http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd')
	SELECT 
	@versaocabecalho=@xmlcabecalho.value('(/cabecalho/@versao)[1]','VARCHAR(4)'),
	@versaodados=@xmlcabecalho.value('(/cabecalho/versaoDados)[1]','VARCHAR(4)')

--------------------------------------------------------------------------------------------------------------------------
---E190	- A versão de dados não existe.
------------------------------------------------------------------------------------------------------------------------
	IF (@versaodados IS NULL) OR (@versaodados <> '1')
		INSERT INTO @listaerros (codigoerro) VALUES ('E190')


------------------------------------------------------------------------------------------------------------------------
---E192 - A versão do cabeçalho não existe.
------------------------------------------------------------------------------------------------------------------------
	IF @versaocabecalho <> '1'
		INSERT INTO @listaerros (codigoerro) VALUES ('E192')

------------------------------------------------------------------------------------------------------------------------
---REGRA DE NEGÓCIO
------------------------------------------------------------------------------------------------------------------------


	IF NOT EXISTS (SELECT codigoerro FROM @listaerros)
	BEGIN
		
		SET @xmlparametro = CONVERT(XML,@xmlentrada)	

		IF @servico = 1 
			EXEC Nfse.sp_WebService_Grava_Lote_RPS @idsessao,@xmlparametro,@xmlretorno OUTPUT
		ELSE
		BEGIN
			IF @servico = 2
				EXEC Nfse.sp_WebService_ConsultarSituacaoLoteRps @xmlparametro,@cnpjautenticacao,@xmlretorno OUTPUT
			ELSE
			BEGIN	
				IF @servico = 3
					EXEC Nfse.sp_WebService_ConsultarNfsePorRPS @xmlparametro,@cnpjautenticacao,@xmlretorno OUTPUT
				ELSE
				BEGIN
					IF @servico = 4
						EXEC Nfse.sp_WebService_ConsultarNfse @xmlparametro,@cnpjautenticacao,@xmlretorno OUTPUT
					ELSE
					BEGIN
						IF @servico = 5
							EXEC Nfse.sp_WebService_ConsultarLoteRps @xmlparametro,@cnpjautenticacao,@xmlretorno OUTPUT
						ELSE
						BEGIN
							IF @servico = 6
								EXEC Nfse.sp_WebService_CancelarNfse @xmlparametro,@cnpjautenticacao,@idsessao,@idxmlretorno OUTPUT, @xmlretorno OUTPUT,@idnotaretorno OUTPUT
							ELSE
							BEGIN
								SET @MsgErro = 'Serviço informado não existe.'	
								GOTO TrataErro
							END	
						END
					END	
				END
			END
		END	

		SELECT @xmlretorno,@idxmlretorno,@idnotaretorno,@idsessao
		

	END
	ELSE
	BEGIN

		;WITH XMLNAMESPACES(DEFAULT 'http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd')
		SELECT @xml = 
		(
		SELECT 
		T1.codigoerro	"Codigo",
		T2.mensagem		"Mensagem",
		T2.solucao		"Correcao"
		FROM
		@listaerros T1 LEFT JOIN Nfse.Erroalerta T2 ON T1.codigoerro = T2.codigo
		FOR XML PATH('MensagemRetorno'), ROOT('ListaMensagemRetorno')
		)
	
		;WITH XMLNAMESPACES(DEFAULT 'http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd')
		SELECT	@xml = CASE @servico 
				WHEN 1 THEN  ( SELECT @xml FOR XML PATH ('EnviarLoteRpsResposta'))
				WHEN 2 THEN  ( SELECT @xml FOR XML PATH ('ConsultarSituacaoLoteRpsResposta'))
				WHEN 3 THEN  ( SELECT @xml FOR XML PATH ('ConsultarNfseRpsResposta'))
				WHEN 4 THEN  ( SELECT @xml FOR XML PATH ('ConsultarNfseResposta'))
				WHEN 5 THEN  ( SELECT @xml FOR XML PATH ('ConsultarLoteRpsResposta'))
				WHEN 6 THEN  ( SELECT @xml FOR XML PATH ('CancelarNfseResposta'))
				END
				

		IF @servico=6
			SELECT @idxmlretorno,@xml,@idnotaretorno,@idsessao
		ELSE
			SELECT	@xml
		
	END
		
	

	RETURN 0	

TrataErro:
	IF @@TRANCOUNT > 0 -- Se houver transação na procedure
		ROLLBACK TRANSACTION
Fim:
	RAISERROR 13000 @MsgErro
	RETURN 1

END

GO

---------------------------------------------------------------------------------------------------

--USE [dbDirectaMaker]
--GO
/****** Object:  StoredProcedure [Nfse].[sp_WebService_CancelarNfse]    Script Date: 12/13/2012 11:17:17 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO


/*
-- =====================================================================================================================
-- Autor:		Marcelo Augusto de Oliveira
-- Criação:		24/05/2012
-- Descrição:	Webservice - Cancelamento de NFS-e passo 1 - retorno XML cancelamento para ser assinado
-- =====================================================================================================================

MANUTENÇÕES (Data - Desenvolvedor - Atualização - Solicitante):

*/

CREATE PROCEDURE [Nfse].[sp_WebService_CancelarNfse]
(
------------------------------------------------------------------------------------------------------------------------
-- PARAMETROS 
------------------------------------------------------------------------------------------------------------------------
	@xmlentrada					XML	= NULL,
	@cnpjcertificado			VARCHAR(14) = NULL,
	@idsessao					INT = NULL,
	@idxmlretorno				VARCHAR(031) = NULL OUTPUT,
	@xmlretorno					XML = NULL OUTPUT,
	@idnotaretorno				INT = NULL OUTPUT
		
)


AS
BEGIN 
	SET NOCOUNT ON		
	SET XACT_ABORT ON




------------------------------------------------------------------------------------------------------------------------
-- VARIÁVEIS
------------------------------------------------------------------------------------------------------------------------
	DECLARE
		@MsgErro						VARCHAR(255),
		@inscricao						VARCHAR(7),
		@cnpj							VARCHAR(14),
		@numeronfse						BIGINT,
		@inscricaomunicipal				VARCHAR(7),
		@codigomunicipioibge			VARCHAR(7),
		@codigomunicipioibgeprestador	VARCHAR(7),
		@idmunicipioprestacaoservico	INT,
		@codigocancelamento				INT,
		@idcadmer						INT,
		@idusuario						INT,
		@login							VARCHAR(20),
		@idstatusnota					INT,
		@competencia					DATETIME,
		@idnfse							VARCHAR(031),
		@nfsexml						VARCHAR(MAX),
		@vencimento						DATETIME,
		@datacancelamento				DATETIME,
		@finalretorno					VARCHAR(MAX),
		@xmlalterado					VARCHAR(MAX),
		@PosicaoInicialTag				INT,
		@PosicaoFinalTag				INT,
		@xml							VARCHAR(MAX)




	DECLARE 
		@listaerros					TABLE	(codigoerro		VARCHAR(4))					


------------------------------------------------------------------------------------------------------------------------
-- INICIALIZAÇÕES DAS VARIÁVEIS
------------------------------------------------------------------------------------------------------------------------

	-- Recuperando informações do XML

	;WITH XMLNAMESPACES(DEFAULT 'http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd')
	SELECT 
	@numeronfse=@xmlentrada.value('(/CancelarNfseEnvio/Pedido/InfPedidoCancelamento/IdentificacaoNfse/Numero)[1]','BIGINT'),
	@cnpj=@xmlentrada.value('(/CancelarNfseEnvio/Pedido/InfPedidoCancelamento/IdentificacaoNfse/Cnpj)[1]','VARCHAR(14)'),
	@inscricao=@xmlentrada.value('(/CancelarNfseEnvio/Pedido/InfPedidoCancelamento/IdentificacaoNfse/InscricaoMunicipal)[1]','VARCHAR(7)'),
	@codigomunicipioibge=@xmlentrada.value('(/CancelarNfseEnvio/Pedido/InfPedidoCancelamento/IdentificacaoNfse/CodigoMunicipio)[1]','VARCHAR(7)'),	
	@codigocancelamento=@xmlentrada.value('(/CancelarNfseEnvio/Pedido/InfPedidoCancelamento/CodigoCancelamento)[1]','INT')
	
------------------------------------------------------------------------------------------------------------------------
-- CRÍTICAS GERAIS
------------------------------------------------------------------------------------------------------------------------
------------------------------------------------------------------------------------------------------------------------
-- Verifica se chamada foi direcionada para o serviço correto
------------------------------------------------------------------------------------------------------------------------
	IF (@numeronfse IS NULL) OR (@cnpj IS NULL) OR (@inscricao IS NULL) OR (@codigomunicipioibge IS NULL) OR (@codigocancelamento IS NULL) 
    BEGIN      
		SET @MsgErro = 'Dados obrigatórios para o serviço não foram preenchidos. Verifique se o serviço chamado está correto.'	
		GOTO TrataErro
	END


	--Obtém idcadmer do prestador para fazer críticas
	SELECT @idcadmer = idcadmer FROM Sis.Cadmer
	WHERE (numerodocumento = @cnpj) AND (inscricao = @inscricao)
------------------------------------------------------------------------------------------------------------------------
-- CRÍTICAS ESPECÍFICAS
------------------------------------------------------------------------------------------------------------------------
	SELECT @idmunicipioprestacaoservico = idmunicipio 
	FROM Sis.Municipio 
	WHERE codigoibge = @codigomunicipioibge

--------------------------------------------------------------------------------------------------------
	--E42 - Código do município da prestação do serviço inválido
-------------------------------------------------------------------------------------------------------
	IF NOT EXISTS(
		SELECT idmunicipio 
		FROM Sis.Municipio 
		WHERE codigoibge = @codigomunicipioibge
		)

		INSERT INTO @listaerros (codigoerro) VALUES ('E42')

------------------------------------------------------------------------------------------------------------------------
	--E46 - CNPJ do prestador não informado
------------------------------------------------------------------------------------------------------------------------
	IF ISNULL(@cnpj,'')=''
		INSERT INTO @listaerros (codigoerro) VALUES ('E46')
------------------------------------------------------------------------------------------------------------------------
	SELECT	@idnotaretorno	= idnota,
			@idstatusnota	= idstatusnota,
			@competencia	= competencia,
			@nfsexml		= nfsexml
	FROM Nfse.nota
	WHERE	idcadmerprestador		= @idcadmer AND 
			numeronfse				= @numeronfse AND
			idmunicipiogerador		= @idmunicipioprestacaoservico

------------------------------------------------------------------------------------------------------------------------
	--E79 - Essa NFS-e já está cancelada
------------------------------------------------------------------------------------------------------------------------
	IF @idstatusnota = 2
		INSERT INTO @listaerros (codigoerro) VALUES ('E79')
------------------------------------------------------------------------------------------------------------------------
	--E142 - Inscrição Municipal do prestador não está vinculada ao CNPJ informado
------------------------------------------------------------------------------------------------------------------------
	IF @idcadmer IS NULL
		INSERT INTO @listaerros (codigoerro) VALUES ('E142')
------------------------------------------------------------------------------------------------------------------------
	--N47 - CNPJ do prestador não confere com o CNPJ do certificado digital
------------------------------------------------------------------------------------------------------------------------
	IF (SUBSTRING(@cnpj,1,7) <> SUBSTRING(@cnpjcertificado,1,7)) OR (@cnpjcertificado IS NULL) OR (@cnpj IS NULL)
		INSERT INTO @listaerros (codigoerro) VALUES ('N47')	
------------------------------------------------------------------------------------------------------------------------
	--N49 - Nenhuma nota fiscal foi encontrada para os parâmetros consultados
------------------------------------------------------------------------------------------------------------------------
	IF @idnotaretorno IS NULL 
		INSERT INTO @listaerros (codigoerro) VALUES ('N49')
------------------------------------------------------------------------------------------------------------------------
	--N50 - Após o vencimento do ISS da NFS-e o cancelamento não pode ser feito via webservice
------------------------------------------------------------------------------------------------------------------------
	
	IF @idnotaretorno IS NOT NULL
	BEGIN
		SET @Vencimento = dbo.fn_CalculaDataVencimentoNota(@competencia)
		IF CONVERT(VARCHAR,GETDATE(),112) > CONVERT(VARCHAR,@Vencimento,112)
			INSERT INTO @listaerros (codigoerro) VALUES ('N50')
	END
------------------------------------------------------------------------------------------------------------------------
	--N51 - Código de cancelamento inválido
------------------------------------------------------------------------------------------------------------------------
	IF NOT EXISTS(	SELECT	descricao
					FROM	Nfse.TipoCancelamento 
					WHERE	idtipocancelamento = @codigocancelamento  )
		INSERT INTO @listaerros (codigoerro) VALUES ('N51')

------------------------------------------------------------------------------------------------------------------------
-- REGRA DE NEGÓCIO
------------------------------------------------------------------------------------------------------------------------

	IF NOT EXISTS (SELECT * FROM @listaerros) 
	BEGIN

		SET @datacancelamento = GETDATE()
		
		UPDATE Nfse.Nota 
		SET idstatusnota = 2,
			motivocancelamento		= 'Cancelamento via Webservice', 
			idtipocancelamento		= @codigocancelamento,
			datacancelamento		= @datacancelamento,
			idusuariocancelamento	= dbo.fn_RetornaIdusuarioSessao(@idsessao),
			idsessao				= @idsessao
		WHERE idnota = @idnotaretorno

		
		-- Montando o XML de retorno alterando o XML do pedido para ser assinado
--		
--		SET @idxmlretorno = 'Cancelamento_'	+ @inscricao + CONVERT(VARCHAR, @numeronfse) + CONVERT(VARCHAR, @datacancelamento, 112)		
--
--		SET @xmlalterado = REPLACE(CONVERT(NVARCHAR(MAX),@xmlentrada),'<CancelarNfseEnvio xmlns="http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd">','<Confirmacao Id="'+@idxmlretorno+'">')
--
--		SET @finalretorno = '<Datahora>'+REPLACE (convert(varchar, getdate(), 120),' ','T')+'</Datahora></Confirmacao>'	
--
--		SET @xmlalterado = REPLACE(CONVERT(NVARCHAR(MAX),@xmlalterado),'</CancelarNfseEnvio>',@finalretorno)

		SET @xml = CONVERT(VARCHAR(MAX),@xmlentrada)

		SET @PosicaoInicialTag = PATINDEX('%<CancelarNfseEnvio%', @xml) 

		------------------------------------------------------------------------------------------------------------------------
		-- E181 - O documento XML de entrada do serviço está fora do padrão
		------------------------------------------------------------------------------------------------------------------------
		If @PosicaoInicialTag = 0 
			INSERT INTO @listaerros (codigoerro) VALUES ('N181')
		ELSE
		BEGIN
			SET @xml = SUBSTRING(@XML,@PosicaoInicialTag,LEN(@XML))
			SET @PosicaoFinalTag = PATINDEX('%>%', SUBSTRING(@XML,1,LEN(@XML)) ) 
		------------------------------------------------------------------------------------------------------------------------
		-- E181 - O documento XML de entrada do serviço está fora do padrão
		------------------------------------------------------------------------------------------------------------------------
			If @PosicaoFinalTag = 0 
				INSERT INTO @listaerros (codigoerro) VALUES ('N181')
			ELSE
			BEGIN
				SET @idxmlretorno = 'Cancelamento_'	+ @inscricao + CONVERT(VARCHAR, @numeronfse) + CONVERT(VARCHAR, @datacancelamento, 112)		
				SET @xmlalterado = REPLACE(@XML,SUBSTRING(@XML,1,@PosicaoFinalTag),'<Confirmacao Id="'+@idxmlretorno+'">')
				SET @finalretorno = '<Datahora>'+REPLACE (convert(varchar, getdate(), 120),' ','T')+'</Datahora></Confirmacao>'	
				SET @xmlalterado = REPLACE(CONVERT(NVARCHAR(MAX),@xmlalterado),'</CancelarNfseEnvio>',@finalretorno)
				SET @xmlalterado =	'<NfseCancelamento>'+
									@xmlalterado +
									'</NfseCancelamento>'
				SET @xmlretorno = CONVERT(XML,@xmlalterado)
			END
		END
	END
	ELSE
	BEGIN

		;WITH XMLNAMESPACES(DEFAULT 'http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd')
		SELECT @xmlretorno = 
		(
		SELECT 
		T1.codigoerro	"Codigo",
		T2.mensagem		"Mensagem",
		T2.solucao		"Correcao"
		FROM
		@listaerros T1 LEFT JOIN Nfse.Erroalerta T2 ON T1.codigoerro = T2.codigo
		FOR XML PATH('MensagemRetorno'),ROOT('ListaMensagemRetorno')
		)
		
		;WITH XMLNAMESPACES(DEFAULT 'http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd')
		SELECT	@xmlretorno = ( SELECT @xmlretorno FOR XML PATH ('CancelarNfseResposta'))

	END
	
	RETURN 0	

TrataErro:
	IF @@TRANCOUNT > 0 -- Se houver transação na procedure
		ROLLBACK TRANSACTION
Fim:
	RAISERROR 13000 @MsgErro
	RETURN 1

END

GO

---------------------------------------------------------------------------------------------------


--USE [dbDirectaMaker]
--GO
/****** Object:  StoredProcedure [Nfse].[sp_WebService_ConsultarLoteRps]    Script Date: 12/13/2012 11:18:06 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO


/*
-- =====================================================================================================================
-- Autor:		Marcelo Augusto de Oliveira
-- Criação:		21/05/2012
-- Descrição:	Webservice - Consultar Lote de Rps
-- =====================================================================================================================

MANUTENÇÕES (Data - Desenvolvedor - Atualização - Solicitante):

*/

CREATE PROCEDURE [Nfse].[sp_WebService_ConsultarLoteRps]
(
------------------------------------------------------------------------------------------------------------------------
-- PARAMETROS 
------------------------------------------------------------------------------------------------------------------------
	@xmlentrada					XML	= NULL,
	@cnpjcertificado			VARCHAR(14) = NULL,
	@xmlretorno					XML = NULL OUTPUT
	
)

AS
BEGIN 
	SET NOCOUNT ON		
	SET XACT_ABORT ON


------------------------------------------------------------------------------------------------------------------------
-- VARIÁVEIS
------------------------------------------------------------------------------------------------------------------------
	DECLARE
		@MsgErro					VARCHAR(255),
		@cnpj						VARCHAR(14),
		@inscricao					VARCHAR(7),
		@protocolo					VARCHAR(50),
		@idcadmer					INT,
		@idusuario					INT,
		@idsessao					INT,
		@login						VARCHAR(20),
		@idnota						INT,
		@xml						VARCHAR(MAX),
		@xmlnfse					VARCHAR(MAX),
		@idsituacaoloterps			INT,
		@idloterps					INT



	DECLARE 
		@listaerros					TABLE	(codigoerro		VARCHAR(4))					

			

------------------------------------------------------------------------------------------------------------------------
-- INICIALIZAÇÕES DAS VARIÁVEIS
------------------------------------------------------------------------------------------------------------------------

	-- Recuperando informações do XML

	;WITH XMLNAMESPACES(DEFAULT 'http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd')
	SELECT 
	@cnpj=@xmlentrada.value('(/ConsultarLoteRpsEnvio/Prestador/Cnpj)[1]','VARCHAR(14)'),
	@inscricao=@xmlentrada.value('(/ConsultarLoteRpsEnvio/Prestador/InscricaoMunicipal)[1]','VARCHAR(7)'),
	@protocolo=@xmlentrada.value('(/ConsultarLoteRpsEnvio/Protocolo)[1]','VARCHAR(50)')
	 	

------------------------------------------------------------------------------------------------------------------------
-- CRÍTICAS GERAIS
------------------------------------------------------------------------------------------------------------------------
	
------------------------------------------------------------------------------------------------------------------------
-- Verifica se chamada foi direcionada para o serviço correto
------------------------------------------------------------------------------------------------------------------------
	IF (@cnpj IS NULL) OR (@inscricao IS NULL) OR (@protocolo IS NULL) 
    BEGIN      
		SET @MsgErro = 'Dados obrigatórios para o serviço não foram preenchidos. Verifique se o serviço chamado está correto.'	
		GOTO TrataErro
	END


--Obtém idcadmer do prestador para fazer críticas
	SELECT @idcadmer = idcadmer FROM Sis.Cadmer
	WHERE (numerodocumento = @cnpj) AND (inscricao = @inscricao)
	
------------------------------------------------------------------------------------------------------------------------
-- CRÍTICAS ESPECÍFICAS
------------------------------------------------------------------------------------------------------------------------

------------------------------------------------------------------------------------------------------------------------
	--E46 - CNPJ do prestador não informado
------------------------------------------------------------------------------------------------------------------------
	IF ISNULL(@cnpj,'')=''
		INSERT INTO @listaerros (codigoerro) VALUES ('E46')
------------------------------------------------------------------------------------------------------------------------
	--E142 - Inscrição Municipal do prestador não está vinculada ao CNPJ informado
------------------------------------------------------------------------------------------------------------------------
	IF @idcadmer IS NULL
		INSERT INTO @listaerros (codigoerro) VALUES ('E142')
------------------------------------------------------------------------------------------------------------------------
	--E160 - Arquivo em desacordo com o XML Schema.
------------------------------------------------------------------------------------------------------------------------
	IF (@cnpj IS NULL) OR (@inscricao IS NULL) OR (@protocolo IS NULL) 
    BEGIN      
		SET @MsgErro = 'Dados obrigatórios para o serviço não foram preenchidos. Verifique se o serviço chamado está correto.'	
		GOTO TrataErro
	END
------------------------------------------------------------------------------------------------------------------------

	-- Obtém o idloterps e idsituacaoloterps para fazer validações

	SELECT	@idloterps			= idloterps,
			@idsituacaoloterps	= idsituacaoloterps
	FROM Nfse.loterps
	WHERE	idcadmer			= @idcadmer AND 
			numeroprotocolo		= @protocolo	

------------------------------------------------------------------------------------------------------------------------
	-- E86 - Número do protocolo de recebimento do lote inexistente na base de dados
------------------------------------------------------------------------------------------------------------------------
	IF NOT EXISTS(	SELECT idloterps FROM Nfse.loterps
					WHERE  idcadmer			= @idcadmer AND 
						   numeroprotocolo	= @protocolo
	  			 )	
			
		INSERT INTO @listaerros (codigoerro) VALUES ('E86')

	ELSE
	BEGIN
------------------------------------------------------------------------------------------------------------------------
	-- E180 - O lote foi recebido mas não foi processado.
------------------------------------------------------------------------------------------------------------------------
		IF @idsituacaoloterps IN (2,5,6,7)
			INSERT INTO @listaerros (codigoerro) VALUES ('E180')
		ELSE
		BEGIN
------------------------------------------------------------------------------------------------------------------------
	-- Lote processado com erro. Retorna lista de erros do processamento
------------------------------------------------------------------------------------------------------------------------
			IF @idsituacaoloterps = 3 
			BEGIN	
				
				;WITH XMLNAMESPACES(DEFAULT 'http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd')
				SELECT @xmlretorno = 
				(
				SELECT 
				T2.codigo	"Codigo",
				
				CASE  
				WHEN numerorps IS NULL THEN T2.mensagem 
				ELSE 'RPS: ' + CONVERT(VARCHAR(50),T1.numerorps) + ' - ' + 'Série: ' + CONVERT(VARCHAR(5),T1.serierps) + ' - '+ T2.mensagem 
				END "Mensagem",
				T2.solucao	"Solucao"
				FROM Nfse.Loterpserroalerta T1
				INNER JOIN Nfse.Erroalerta T2
				ON T1.Iderroalerta = T2.iderroalerta
				INNER JOIN Nfse.Loterps T3
				ON T1.Idloterps = T3.idloterps
				INNER JOIN Sis.Cadmer T4
				ON T3.Idcadmer = T4.idcadmer
				WHERE t1.idloterps = @idloterps
				FOR XML PATH('MensagemRetorno'),ROOT('ListaMensagemRetorno')
				)
				
				;WITH XMLNAMESPACES(DEFAULT 'http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd')
				SELECT	@xmlretorno = ( SELECT @xmlretorno FOR XML PATH ('ConsultarLoteRpsResposta'))
			END
			ELSE
			BEGIN
				IF @idsituacaoloterps = 4
				BEGIN
				------------------------------------------------------------------------------------------------------------------------
					--N49 - Nenhuma nota fiscal foi encontrada para os parâmetros consultados
				------------------------------------------------------------------------------------------------------------------------
					IF NOT EXISTS(	SELECT T1.idnota 
									FROM Nfse.nota	T1 
									INNER JOIN Nfse.rps	T2		ON T1.idrps = T2.idrps
									INNER JOIN Nfse.loterps T3 	ON T2.idloterps = T3.idloterps
									WHERE	T3.idloterps = @idloterps
	  							 )	
							
						INSERT INTO @listaerros (codigoerro) VALUES ('N49')
				END
			END
		END
	END


------------------------------------------------------------------------------------------------------------------------
	--N47 - CNPJ do prestador não confere com o CNPJ do certificado digital
------------------------------------------------------------------------------------------------------------------------
--	IF (SUBSTRING(@cnpj,1,7) <> SUBSTRING(@cnpjcertificado,1,7)) OR (@cnpjcertificado IS NULL) OR (@cnpj IS NULL)
--		INSERT INTO @listaerros (codigoerro) VALUES ('N47')	


------------------------------------------------------------------------------------------------------------------------
-- REGRA DE NEGÓCIO
------------------------------------------------------------------------------------------------------------------------

	IF NOT EXISTS (SELECT * FROM @listaerros) 
	BEGIN
		IF @idsituacaoloterps = 4
		BEGIN

			DECLARE cursor_nota CURSOR FOR 
			SELECT t1.idnota,t1.nfsexml
			FROM Nfse.nota	t1 
			INNER JOIN Nfse.rps	t2		ON t1.idrps = t2.idrps
			INNER JOIN Nfse.loterps t3 	ON t2.idloterps = t3.idloterps
			WHERE	t3.idcadmer			= @idcadmer AND 
					t3.numeroprotocolo	= @protocolo
			
			OPEN cursor_nota

			FETCH NEXT FROM cursor_nota
			INTO @idnota, @xmlnfse

			SET @xml = ''

			WHILE @@FETCH_STATUS = 0

			BEGIN	
				SET @xml =@xml + @xmlnfse
				FETCH NEXT FROM cursor_nota
				INTO @idnota, @xmlnfse

			END

			CLOSE cursor_nota
			DEALLOCATE cursor_nota

			;WITH XMLNAMESPACES(DEFAULT 'http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd')
			SELECT	@xmlretorno = ( SELECT CONVERT(XML,@xml) FOR XML PATH ('ListaNfse'))

			;WITH XMLNAMESPACES(DEFAULT 'http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd')
			SELECT	@xmlretorno = ( SELECT @xmlretorno FOR XML PATH ('ConsultarLoteRpsResposta'))

		END

	END
	ELSE
	BEGIN

		;WITH XMLNAMESPACES(DEFAULT 'http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd')
		SELECT @xmlretorno = 
		(
		SELECT 
		T1.codigoerro	"Codigo",
		T2.mensagem		"Mensagem",
		T2.solucao		"Correcao"
		FROM
		@listaerros T1 LEFT JOIN Nfse.Erroalerta T2 ON T1.codigoerro = T2.codigo
		FOR XML PATH('MensagemRetorno'),ROOT('ListaMensagemRetorno')
		)
		
		;WITH XMLNAMESPACES(DEFAULT 'http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd')
		SELECT	@xmlretorno = ( SELECT @xmlretorno FOR XML PATH ('ConsultarLoteRpsResposta'))


	END
	
	RETURN 0	

TrataErro:
	IF @@TRANCOUNT > 0 -- Se houver transação na procedure
		ROLLBACK TRANSACTION
Fim:
	RAISERROR 13000 @MsgErro
	RETURN 1

END

GO

---------------------------------------------------------------------------------------------------

--USE [dbDirectaMaker]
--GO
/****** Object:  StoredProcedure [Nfse].[sp_WebService_ConsultarNfse]    Script Date: 12/13/2012 11:26:31 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO


/*
-- =====================================================================================================================
-- Autor:		Marcelo Augusto de Oliveira
-- Criação:		21/05/2012
-- Descrição:	Webservice - Consultar NFS-e
-- =====================================================================================================================

MANUTENÇÕES (Data - Desenvolvedor - Atualização - Solicitante):

*/

CREATE PROCEDURE [Nfse].[sp_WebService_ConsultarNfse]
(
------------------------------------------------------------------------------------------------------------------------
-- PARAMETROS 
------------------------------------------------------------------------------------------------------------------------
	@xmlentrada					XML	= NULL,
	@cnpjcertificado			VARCHAR(14) = NULL,
	@xmlretorno					XML = NULL OUTPUT
	
)

AS
BEGIN 
	SET NOCOUNT ON		
	SET XACT_ABORT ON


------------------------------------------------------------------------------------------------------------------------
-- VARIÁVEIS
------------------------------------------------------------------------------------------------------------------------
	DECLARE
		@MsgErro					VARCHAR(255),
		@cnpj						VARCHAR(14),
		@inscricao					VARCHAR(7),
		@numeronfse					BIGINT,
		@datainicial				DATETIME,
		@datafinal					DATETIME,
		@inscricaomunicipal			VARCHAR(7),
		@cnpjtomador				VARCHAR(14),
		@cpftomador					VARCHAR(11),
		@documentotomador			VARCHAR(14),
		@inscricaotomador			VARCHAR(7),
		@idcadmer					INT,
		@idcadmertomador			INT,
		@idusuario					INT,
		@idsessao					INT,
		@login						VARCHAR(20),
		@idnota						INT,
		@xml						VARCHAR(MAX),
		@xmlnfse					VARCHAR(MAX),
		@Sql						NVARCHAR(MAX),
		@notas						INT



	DECLARE 
		@listaerros					TABLE	(codigoerro		VARCHAR(4))					

			

------------------------------------------------------------------------------------------------------------------------
-- INICIALIZAÇÕES DAS VARIÁVEIS
------------------------------------------------------------------------------------------------------------------------

	-- Recuperando informações do XML

	;WITH XMLNAMESPACES(DEFAULT 'http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd')
	SELECT 
	@cnpj=@xmlentrada.value('(/ConsultarNfseEnvio/Prestador/Cnpj)[1]','VARCHAR(14)'),
	@inscricao=@xmlentrada.value('(/ConsultarNfseEnvio/Prestador/InscricaoMunicipal)[1]','VARCHAR(7)'),
	@numeronfse=@xmlentrada.value('(/ConsultarNfseEnvio/NumeroNfse)[1]','BIGINT'),
	@datainicial=@xmlentrada.value('(/ConsultarNfseEnvio/PeriodoEmissao/DataInicial)[1]','VARCHAR(10)'),
	@datafinal=@xmlentrada.value('(/ConsultarNfseEnvio/PeriodoEmissao/DataFinal)[1]','VARCHAR(10)'),
	@cnpjtomador=@xmlentrada.value('(/ConsultarNfseEnvio/Tomador/CpfCnpj/Cnpj)[1]','VARCHAR(14)'),
	@cpftomador=@xmlentrada.value('(/ConsultarNfseEnvio/Tomador/CpfCnpj/Cpf)[1]','VARCHAR(11)'),
	@inscricaotomador=@xmlentrada.value('(/ConsultarNfseEnvio/Tomador/InscricaoMunicipal)[1]','VARCHAR(7)')

	IF (@datainicial IS NOT NULL) AND (@datafinal IS NOT NULL)
	BEGIN
		SET @datainicial = @datainicial+' 00:00:00'
		SET @datafinal = @datafinal+' 23:59:59'
	END

	IF (@cnpjtomador IS NOT NULL) OR (@cpftomador IS NOT NULL)
		SET @documentotomador = COALESCE(@cnpjtomador,@cpftomador)

------------------------------------------------------------------------------------------------------------------------
-- CRÍTICAS GERAIS
------------------------------------------------------------------------------------------------------------------------
------------------------------------------------------------------------------------------------------------------------
-- Verifica se chamada foi direcionada para o serviço correto
------------------------------------------------------------------------------------------------------------------------
	IF (@cnpj IS NULL) OR (@inscricao IS NULL) OR (@datainicial IS NULL)  OR (@datafinal IS NULL) 
    BEGIN      
		SET @MsgErro = 'Dados obrigatórios para o serviço não foram preenchidos. Verifique se o serviço chamado está correto.'	
		GOTO TrataErro
	END


	--Obtém idcadmer do prestador para fazer críticas
	SELECT @idcadmer = idcadmer FROM Sis.Cadmer
	WHERE (numerodocumento = @cnpj) AND (inscricao = @inscricao)

	--Obtém idcadmer do tomador para fazer críticas
	SELECT @idcadmertomador = idcadmer FROM Sis.Cadmer
	WHERE (numerodocumento = @cnpjtomador) AND (inscricao = @inscricaotomador)
------------------------------------------------------------------------------------------------------------------------
-- CRÍTICAS ESPECÍFICAS
------------------------------------------------------------------------------------------------------------------------

------------------------------------------------------------------------------------------------------------------------
	--E46 - CNPJ do prestador não informado
------------------------------------------------------------------------------------------------------------------------
	IF ISNULL(@cnpj,'')=''
		INSERT INTO @listaerros (codigoerro) VALUES ('E46')
------------------------------------------------------------------------------------------------------------------------
	--E131 - Campo data inicial preenchido incorretamente
------------------------------------------------------------------------------------------------------------------------
	IF	(@datainicial IS NOT NULL) AND (ISDATE(@datainicial) <> 1)
		INSERT INTO @listaerros (codigoerro) VALUES ('E131')
------------------------------------------------------------------------------------------------------------------------
	--E132 - Campo data final preenchido incorretamente
------------------------------------------------------------------------------------------------------------------------
	IF (@datafinal IS NOT NULL) AND ISDATE(@datafinal) <> 1 
		INSERT INTO @listaerros (codigoerro) VALUES ('E132')
------------------------------------------------------------------------------------------------------------------------
	--E133 - Data final da pesquisa não poderá ser superior a data de hoje.
------------------------------------------------------------------------------------------------------------------------
	IF (@datafinal IS NOT NULL) AND DATEDIFF(dd,GETDATE(),@datafinal) > 0
		INSERT INTO @listaerros (codigoerro) VALUES ('E133')
------------------------------------------------------------------------------------------------------------------------
	--E134 - A data final não poderá ser anterior à data inicial
------------------------------------------------------------------------------------------------------------------------
	IF ((@datainicial IS NOT NULL) AND (@datafinal IS NOT NULL)) AND (@datafinal < @datainicial) 
		INSERT INTO @listaerros (codigoerro) VALUES ('E134')
------------------------------------------------------------------------------------------------------------------------
	--E142 - Inscrição Municipal do prestador não está vinculada ao CNPJ informado
------------------------------------------------------------------------------------------------------------------------
	IF @idcadmer IS NULL
		INSERT INTO @listaerros (codigoerro) VALUES ('E142')

------------------------------------------------------------------------------------------------------------------------
	--E143 - Inscrição Municipal do tomador não está vinculada ao CNPJ informado.
------------------------------------------------------------------------------------------------------------------------
	IF (@idcadmertomador IS NULL) AND (ISNULL(@cnpjtomador,'')<>'') AND (ISNULL(@inscricaotomador,'')<>'')
		INSERT INTO @listaerros (codigoerro) VALUES ('E143')
------------------------------------------------------------------------------------------------------------------------
	--N47 - CNPJ do prestador não confere com o CNPJ do certificado digital
------------------------------------------------------------------------------------------------------------------------
	IF (SUBSTRING(@cnpj,1,7) <> SUBSTRING(@cnpjcertificado,1,7)) OR (@cnpjcertificado IS NULL) OR (@cnpj IS NULL)
		INSERT INTO @listaerros (codigoerro) VALUES ('N47')	
------------------------------------------------------------------------------------------------------------------------
	--N48 - O período de pesquisa não poderá ser superior a 6 meses
------------------------------------------------------------------------------------------------------------------------
	IF ((@datainicial IS NOT NULL) AND (@datafinal IS NOT NULL)) AND (DATEDIFF(m,@datafinal,@datainicial) > 6)
		INSERT INTO @listaerros (codigoerro) VALUES ('N48')
------------------------------------------------------------------------------------------------------------------------
	--N49 - Nenhuma nota fiscal foi encontrada para os parâmetros consultados
------------------------------------------------------------------------------------------------------------------------

	SET @Sql = 'SELECT @notas = COUNT(*) FROM Nfse.nota'

	SET @Sql = @Sql + ' WHERE idcadmerprestador ='+CONVERT(VARCHAR(50),@idcadmer)
	
	IF @numeronfse IS NOT NULL
		SET @Sql = @Sql + ' AND numeronfse ='+ CONVERT(VARCHAR(50),@numeronfse)

	IF (@datainicial IS NOT NULL) AND (@datafinal IS NOT NULL) 
		SET @Sql = @Sql + ' AND ((dataemissao >= '''+CONVERT(VARCHAR(30),@datainicial,121)+''') AND (dataemissao <='''+CONVERT(VARCHAR(30),@datafinal,121)+'''))'
		
	IF (@documentotomador IS NOT NULL)
		SET @Sql = @Sql + ' AND cpfcnpjtomador ='''+@documentotomador+''''

	IF (@inscricaotomador IS NOT NULL)
		SET @Sql = @Sql + ' AND inscricaomunicipaltomador ='''+ @inscricaotomador+''''
		

	EXEC sp_executesql @Sql,N'@notas INT OUT',@notas OUTPUT

	IF ISNULL(@notas,0) = 0
			
		INSERT INTO @listaerros (codigoerro) VALUES ('N49')

------------------------------------------------------------------------------------------------------------------------
-- REGRA DE NEGÓCIO
------------------------------------------------------------------------------------------------------------------------

	IF NOT EXISTS (SELECT * FROM @listaerros) 
	BEGIN

		
		SET @Sql = 'DECLARE cursor_nota CURSOR FOR SELECT idnota,nfsexml FROM Nfse.nota'

		SET @Sql = @Sql + ' WHERE idcadmerprestador ='+CONVERT(VARCHAR(50),@idcadmer)
		
		IF @numeronfse IS NOT NULL
			SET @Sql = @Sql + ' AND numeronfse ='+ CONVERT(VARCHAR(50),@numeronfse)

		IF (@datainicial IS NOT NULL) AND (@datafinal IS NOT NULL) 
			SET @Sql = @Sql + ' AND ((dataemissao >= '''+CONVERT(VARCHAR(30),@datainicial,121)+''') AND (dataemissao <='''+CONVERT(VARCHAR(30),@datafinal,121)+'''))'
			
		IF (@documentotomador IS NOT NULL)
			SET @Sql = @Sql + ' AND cpfcnpjtomador ='''+@documentotomador+''''

		IF (@inscricaotomador IS NOT NULL)
			SET @Sql = @Sql + ' AND inscricaomunicipaltomador ='''+ @inscricaotomador+''''

		EXEC (@Sql) OPEN cursor_nota

		FETCH NEXT FROM cursor_nota
		INTO @idnota, @xmlnfse

		SET @xml = ''

		WHILE @@FETCH_STATUS = 0

		BEGIN	
			SET @xml = @xml+@xmlnfse
			
			FETCH NEXT FROM cursor_nota
			INTO @idnota, @xmlnfse
		END

		CLOSE cursor_nota
		DEALLOCATE cursor_nota

		;WITH XMLNAMESPACES(DEFAULT 'http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd')
		SELECT	@xmlretorno = ( SELECT CONVERT(XML,@xml) FOR XML PATH ('ListaNfse'))

		;WITH XMLNAMESPACES(DEFAULT 'http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd')
		SELECT	@xmlretorno = ( SELECT @xmlretorno FOR XML PATH ('ConsultarNfseResposta'))



	END
	ELSE
	BEGIN

		;WITH XMLNAMESPACES(DEFAULT 'http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd')
		SELECT @xmlretorno = 
		(
		SELECT 
		T1.codigoerro	"Codigo",
		T2.mensagem		"Mensagem",
		T2.solucao		"Correcao"
		FROM
		@listaerros T1 LEFT JOIN Nfse.Erroalerta T2 ON T1.codigoerro = T2.codigo
		FOR XML PATH('MensagemRetorno'),ROOT('ListaMensagemRetorno')
		)
		
		;WITH XMLNAMESPACES(DEFAULT 'http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd')
		SELECT	@xmlretorno = ( SELECT @xmlretorno FOR XML PATH ('ConsultarNfseResposta'))

	END
	
	RETURN 0	

TrataErro:
	IF @@TRANCOUNT > 0 -- Se houver transação na procedure
		ROLLBACK TRANSACTION
Fim:
	RAISERROR 13000 @MsgErro
	RETURN 1

END

GO

---------------------------------------------------------------------------------------------------

--USE [dbDirectaMaker]
--GO
/****** Object:  StoredProcedure [Nfse].[sp_WebService_ConsultarNfsePorRPS]    Script Date: 12/13/2012 11:27:34 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO


/*
-- =====================================================================================================================
-- Autor:		Marcelo Augusto de Oliveira
-- Criação:		14/03/2012
-- Descrição:	Webservice - Consultar NFS-e por RPS
-- =====================================================================================================================

MANUTENÇÕES (Data - Desenvolvedor - Atualização - Solicitante):

*/

CREATE PROCEDURE [Nfse].[sp_WebService_ConsultarNfsePorRPS]
(
------------------------------------------------------------------------------------------------------------------------
-- PARAMETROS 
------------------------------------------------------------------------------------------------------------------------
	@xmlentrada					XML	= NULL,
	@cnpjcertificado			VARCHAR(14) = NULL,
	@xmlretorno					XML = NULL OUTPUT
	
)

AS
BEGIN 
	SET NOCOUNT ON		
	SET XACT_ABORT ON


------------------------------------------------------------------------------------------------------------------------
-- VARIÁVEIS
------------------------------------------------------------------------------------------------------------------------
	DECLARE
		@MsgErro					VARCHAR(255),
		@numero						BIGINT,
		@serie						VARCHAR(5),
		@tipo						INT,
		@cnpj						VARCHAR(14),
		@inscricao					VARCHAR(7),
		@inscricaomunicipal			VARCHAR(7),
		@idcadmer					INT,
		@idusuario					INT,
		@idsessao					INT,
		@login						VARCHAR(20),
		@idnota						INT,
		@xml						VARCHAR(MAX)
	DECLARE 
		@listaerros					TABLE	(codigoerro		VARCHAR(4))					

			

------------------------------------------------------------------------------------------------------------------------
-- INICIALIZAÇÕES DAS VARIÁVEIS
------------------------------------------------------------------------------------------------------------------------

	-- Recuperando informações do XML

	;WITH XMLNAMESPACES(DEFAULT 'http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd')
	SELECT 
	@numero=@xmlentrada.value('(/ConsultarNfseRpsEnvio/IdentificacaoRps/Numero)[1]','BIGINT'),
	@serie=@xmlentrada.value('(/ConsultarNfseRpsEnvio/IdentificacaoRps/Serie)[1]','VARCHAR(5)'),
	@tipo=@xmlentrada.value('(/ConsultarNfseRpsEnvio/IdentificacaoRps/Tipo)[1]','INT'),
	@cnpj=@xmlentrada.value('(/ConsultarNfseRpsEnvio/Prestador/Cnpj)[1]','VARCHAR(14)'),
	@inscricao=@xmlentrada.value('(/ConsultarNfseRpsEnvio/Prestador/InscricaoMunicipal)[1]','VARCHAR(7)')
	
------------------------------------------------------------------------------------------------------------------------
-- CRÍTICAS GERAIS
------------------------------------------------------------------------------------------------------------------------
------------------------------------------------------------------------------------------------------------------------
	--Verifica se chamada foi direcionada para o serviço correto
------------------------------------------------------------------------------------------------------------------------
	IF (@cnpj IS NULL) OR (@inscricao IS NULL) OR (@numero IS NULL)  OR (@serie IS NULL)  OR (@tipo IS NULL) 
    BEGIN      
		SET @MsgErro = 'Dados obrigatórios para o serviço não foram preenchidos. Verifique se o serviço chamado está correto.'	
		GOTO TrataErro
	END



	--Obtém idcadmer para fazer críticas
	SELECT @idcadmer = idcadmer FROM Sis.Cadmer
	WHERE (numerodocumento = @cnpj) AND (inscricao = @inscricao)

------------------------------------------------------------------------------------------------------------------------
-- CRÍTICAS ESPECÍFICAS
------------------------------------------------------------------------------------------------------------------------

------------------------------------------------------------------------------------------------------------------------
	--E4 - Esse RPS não foi encontrado em nossa base de dados.
------------------------------------------------------------------------------------------------------------------------	
	
	IF NOT EXISTS (SELECT idrps FROM Nfse.Rps WHERE numero=@numero AND serie = @serie AND idtiporps = @tipo)
	   INSERT INTO @listaerros (codigoerro) VALUES ('E4')
	
------------------------------------------------------------------------------------------------------------------------
	--E11 -	Número do RPS não informado.
------------------------------------------------------------------------------------------------------------------------

	IF (@numero IS NULL)
		INSERT INTO @listaerros (codigoerro) VALUES ('E11')

------------------------------------------------------------------------------------------------------------------------
	--E12 -	Tipo do RPS  não informado.
------------------------------------------------------------------------------------------------------------------------
	IF (@tipo IS NULL)
		INSERT INTO @listaerros (codigoerro) VALUES ('E12')

------------------------------------------------------------------------------------------------------------------------
	--E13 - RPS inválido. 
------------------------------------------------------------------------------------------------------------------------
	IF NOT EXISTS (SELECT idtiporps FROM Nfse.Tiporps WHERE idtiporps = @tipo)
		INSERT INTO @listaerros (codigoerro) VALUES ('E13')

------------------------------------------------------------------------------------------------------------------------
	--E46 - CNPJ do prestador não informado
------------------------------------------------------------------------------------------------------------------------
	IF ISNULL(@cnpj,'')=''
		INSERT INTO @listaerros (codigoerro) VALUES ('E46')

------------------------------------------------------------------------------------------------------------------------
	--N47 - CNPJ do prestador não confere com o CNPJ do certificado digital
------------------------------------------------------------------------------------------------------------------------
	IF (SUBSTRING(@cnpj,1,7) <> SUBSTRING(@cnpjcertificado,1,7)) OR (@cnpjcertificado IS NULL) OR (@cnpj IS NULL)
		INSERT INTO @listaerros (codigoerro) VALUES ('N47')	

------------------------------------------------------------------------------------------------------------------------
	--E89 - Não existe na base de dados uma NFS-e emitida para o número de RPS informado
------------------------------------------------------------------------------------------------------------------------
		
	SELECT @idnota=T1.idnota,@xml=nfsexml FROM nfse.nota T1
	INNER JOIN nfse.rps T2 ON T1.idnota = T2.idnota
	WHERE T2.numero = @numero AND T2.serie = @serie AND T2.idtiporps = @tipo AND T1.idcadmerprestador = @idcadmer
	
	IF @idnota IS NULL
		INSERT INTO @listaerros (codigoerro) VALUES ('E89')

------------------------------------------------------------------------------------------------------------------------
	--E142 - Inscrição Municipal do prestador não está vinculada ao CNPJ informado
------------------------------------------------------------------------------------------------------------------------
	IF @idcadmer IS NULL
		INSERT INTO @listaerros (codigoerro) VALUES ('E142')

------------------------------------------------------------------------------------------------------------------------
-- REGRA DE NEGÓCIO
------------------------------------------------------------------------------------------------------------------------
	
	IF NOT EXISTS (SELECT * FROM @listaerros)
	BEGIN
		;WITH XMLNAMESPACES(DEFAULT 'http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd')
		SELECT	@xmlretorno = ( SELECT @xml FOR XML PATH ('ConsultarNfseRpsResposta'))
	END
	ELSE
	BEGIN

		;WITH XMLNAMESPACES(DEFAULT 'http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd')
		SELECT @xml = 
		(
		SELECT 
		T1.codigoerro	"Codigo",
		T2.mensagem		"Mensagem",
		T2.solucao		"Correcao"
		FROM
		@listaerros T1 LEFT JOIN Nfse.Erroalerta T2 ON T1.codigoerro = T2.codigo
		FOR XML PATH('MensagemRetorno'),ROOT('ListaMensagemRetorno')
		)
		
		;WITH XMLNAMESPACES(DEFAULT 'http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd')
		SELECT	@xmlretorno = ( SELECT @xml FOR XML PATH ('ConsultarNfseRpsResposta'))

	END
	
	RETURN 0	

TrataErro:
	IF @@TRANCOUNT > 0 -- Se houver transação na procedure
		ROLLBACK TRANSACTION
Fim:
	RAISERROR 13000 @MsgErro
	RETURN 1

END

GO

---------------------------------------------------------------------------------------------------

--USE [dbDirectaMaker]
--GO
/****** Object:  StoredProcedure [Nfse].[sp_WebService_ConsultarSituacaoLoteRps]    Script Date: 12/13/2012 11:28:14 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO


/*
-- =====================================================================================================================
-- Autor:		Marcelo Augusto de Oliveira
-- Criação:		14/03/2012
-- Descrição:	Webservice - Consulta de Situação de Lote de RPS
-- =====================================================================================================================

MANUTENÇÕES (Data - Desenvolvedor - Atualização - Solicitante):

*/

CREATE PROCEDURE [Nfse].[sp_WebService_ConsultarSituacaoLoteRps]
(
------------------------------------------------------------------------------------------------------------------------
-- PARAMETROS 
------------------------------------------------------------------------------------------------------------------------
	@xmlentrada					XML	= NULL,
	@cnpjcertificado			VARCHAR(14) = NULL,
	@xmlretorno					XML = NULL OUTPUT
	
)

AS
BEGIN 
	SET NOCOUNT ON		
	SET XACT_ABORT ON


------------------------------------------------------------------------------------------------------------------------
-- VARIÁVEIS
------------------------------------------------------------------------------------------------------------------------
	DECLARE
		@MsgErro					VARCHAR(255),
		@cnpj						VARCHAR(14),
		@inscricao					VARCHAR(7),
		@protocolo					VARCHAR(50),
		@inscricaomunicipal			VARCHAR(7),
		@idcadmer					INT,
		@idusuario					INT,
		@idsessao					INT,
		@login						VARCHAR(20),
		@idnota						INT,
		@xml						XML
	DECLARE 
		@listaerros					TABLE	(codigoerro		VARCHAR(4))					

			

------------------------------------------------------------------------------------------------------------------------
-- INICIALIZAÇÕES DAS VARIÁVEIS
------------------------------------------------------------------------------------------------------------------------

	-- Recuperando informações do XML

	;WITH XMLNAMESPACES(DEFAULT 'http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd')
	SELECT 
	
	@cnpj=@xmlentrada.value('(/ConsultarSituacaoLoteRpsEnvio/Prestador/Cnpj)[1]','VARCHAR(14)'),
	@inscricao=@xmlentrada.value('(/ConsultarSituacaoLoteRpsEnvio/Prestador/InscricaoMunicipal)[1]','VARCHAR(7)'),
	@protocolo = @xmlentrada.value('(/ConsultarSituacaoLoteRpsEnvio/Protocolo)[1]','VARCHAR(50)')
	
------------------------------------------------------------------------------------------------------------------------
-- CRÍTICAS
------------------------------------------------------------------------------------------------------------------------
	
------------------------------------------------------------------------------------------------------------------------
-- Verifica se chamada foi direcionada para o serviço correto
------------------------------------------------------------------------------------------------------------------------
	IF (@cnpj IS NULL) OR (@inscricao IS NULL) OR (@protocolo IS NULL) 
    BEGIN      
		SET @MsgErro = 'Dados obrigatórios para o serviço não foram preenchidos. Verifique se o serviço chamado está correto.'	
		GOTO TrataErro
	END


	--Obtem idcadmer para fazer críticas
	SELECT @idcadmer = idcadmer FROM Sis.Cadmer
	WHERE (numerodocumento = @cnpj) AND (inscricao = ISNULL(@inscricao,inscricao))

------------------------------------------------------------------------------------------------------------------------
	--E46 - CNPJ do prestador não informado
------------------------------------------------------------------------------------------------------------------------
	IF ISNULL(@cnpj,'')=''
		INSERT INTO @listaerros (codigoerro) VALUES ('E46')

------------------------------------------------------------------------------------------------------------------------
	--E86 - Número do protocolo de recebimento do lote inexistente na base de dados
------------------------------------------------------------------------------------------------------------------------	
	IF NOT EXISTS (SELECT idloterps FROM Nfse.Loterps WHERE numeroprotocolo = @protocolo AND idcadmer = @idcadmer)
	   INSERT INTO @listaerros (codigoerro) VALUES ('E86')

------------------------------------------------------------------------------------------------------------------------
	--N47 - CNPJ do prestador não confere com o CNPJ do certificado digital
------------------------------------------------------------------------------------------------------------------------
	IF (SUBSTRING(@cnpj,1,7) <> SUBSTRING(@cnpjcertificado,1,7)) OR (@cnpjcertificado IS NULL) OR (@cnpj IS NULL)
		INSERT INTO @listaerros (codigoerro) VALUES ('N47')	

------------------------------------------------------------------------------------------------------------------------
	--E142 - Inscrição Municipal do prestador não está vinculada ao CNPJ informado
------------------------------------------------------------------------------------------------------------------------
	IF @idcadmer IS NULL
		INSERT INTO @listaerros (codigoerro) VALUES ('E142')
------------------------------------------------------------------------------------------------------------------------
-- REGRA DE NEGÓCIO
--	Código de situação de lote de RPS (Modelo Conceitual)
	--1 – Não Recebido 
	--2 – Não Processado 
	--3 – Processado com Erro 
	--4 – Processado com Sucesso 
------------------------------------------------------------------------------------------------------------------------
	
	IF NOT EXISTS (SELECT * FROM @listaerros)
	BEGIN
		;WITH XMLNAMESPACES(DEFAULT 'http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd')
		SELECT	@xmlretorno = (
				SELECT	numerolote									"NumeroLote",
				CASE 
				WHEN idsituacaoloterps <=4 THEN idsituacaoloterps
				WHEN idsituacaoloterps >4 THEN 2
				END													"Situacao"
				FROM Nfse.Loterps
				WHERE numeroprotocolo = @protocolo AND idcadmer = @idcadmer
				FOR XML PATH('ConsultarSituacaoLoteRpsResposta')
				)
	END
	ELSE
	BEGIN		
		;WITH XMLNAMESPACES(DEFAULT 'http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd')
		SELECT @xml = 
		(
		SELECT 
		T1.codigoerro	"Codigo",
		T2.mensagem		"Mensagem",
		T2.solucao		"Correcao"
		FROM
		@listaerros T1 LEFT JOIN Nfse.Erroalerta T2 ON T1.codigoerro = T2.codigo
		FOR XML PATH('MensagemRetorno'), ROOT('ListaMensagemRetorno')
		)

		;WITH XMLNAMESPACES(DEFAULT 'http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd')
		SELECT	@xmlretorno = (SELECT @xml FOR XML PATH ('ConsultarSituacaoLoteRpsResposta'))

	END
	
	RETURN 0	

TrataErro:
	IF @@TRANCOUNT > 0 -- Se houver transação na procedure
		ROLLBACK TRANSACTION
Fim:
	RAISERROR 13000 @MsgErro
	RETURN 1

END

GO

---------------------------------------------------------------------------------------------------


USE [dbDirectaMaker]
GO
/****** Object:  StoredProcedure [Nfse].[sp_WebService_Grava_Lote_RPS]    Script Date: 12/13/2012 11:30:12 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO


/*
-- =====================================================================================================================
-- Autor:		Marcelo Augusto de Oliveira
-- Criação:		13/09/2010
-- Descrição:	Grava Lote de RPS enviado
-- =====================================================================================================================

MANUTENÇÕES (Data - Desenvolvedor - Atualização - Solicitante):
-- 11/05/2011 - Marcelo - Validação do Id (Lote e RPS) x URI (Assinaturas Lote e RPS). Se houver diferença aborta o processo - Rafael


*/

CREATE    PROCEDURE [Nfse].[sp_WebService_Grava_Lote_RPS]
(
------------------------------------------------------------------------------------------------------------------------
-- PARAMETROS 
------------------------------------------------------------------------------------------------------------------------
	@idsessao					INT = NULL	, 
	@xmlentrada					XML	= NULL	,
	@xmlretorno					XML = NULL OUTPUT	
	

)
AS
BEGIN 
	SET NOCOUNT ON		
	SET XACT_ABORT ON


------------------------------------------------------------------------------------------------------------------------
-- VARIÁVEIS
------------------------------------------------------------------------------------------------------------------------
	DECLARE
		@MsgErro					VARCHAR(255),
		@idloterps					VARCHAR(255), 
		@idloterpsxml				VARCHAR(255), 
		@codigosituacaoloterps		INT,
		@idsituacaoloterpsgravar	INT,
		@idusuario					INT, 
		@idcadmer					INT, 
		@numerolote					BIGINT, 
		@quantidaderps				INT, 
		@quantidaderpscalc			INT,
		@lotexml					XML, 
		@xmlvalido					XML(NfseNovo),
		@usuarioinclusao			VARCHAR(50), 
		@datainclusao				DATETIME,
		@cnpj						VARCHAR(14),
		@inscricaomunicipal			VARCHAR(7),
		@idlote						VARCHAR(255),
		@URIassinatura				VARCHAR(255),
		@errosassinaLote			INT,
		@errosassinarps				INT,
		@xml						XML

DECLARE
		@listaerros					TABLE	(codigoerro		VARCHAR(4))	

------------------------------------------------------------------------------------------------------------------------
---E160 - Arquivo em desacordo com o XML Schema.
------------------------------------------------------------------------------------------------------------------------
		
	BEGIN TRY
		SET @xmlvalido = @xmlentrada				
	END TRY
	BEGIN CATCH
		    
       INSERT INTO @listaerros (codigoerro) VALUES ('E160')
		
	END CATCH
------------------------------------------------------------------------------------------------------------------------
-- INICIALIZAÇÕES DAS VARIÁVEIS
------------------------------------------------------------------------------------------------------------------------
			
	-- Recuperando informações do Lote Rps do XML

	;WITH XMLNAMESPACES(DEFAULT 'http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd')
	SELECT 
	@idloterpsxml = @xmlentrada.value('(/EnviarLoteRpsEnvio/LoteRps/@Id)[1]','VARCHAR(255)'),
	@numerolote = @xmlentrada.value('(/EnviarLoteRpsEnvio/LoteRps/NumeroLote)[1]','BIGINT'),
	@cnpj = @xmlentrada.value('(/EnviarLoteRpsEnvio/LoteRps/Cnpj)[1]','varchar(14)'),
	@inscricaomunicipal = @xmlentrada.value('(/EnviarLoteRpsEnvio/LoteRps/InscricaoMunicipal)[1]','varchar(7)'),
	@quantidaderps = @xmlentrada.value('(/EnviarLoteRpsEnvio/LoteRps/QuantidadeRps)[1]','int')
	
	
------------------------------------------------------------------------------------------------------------------------
-- CRÍTICAS
------------------------------------------------------------------------------------------------------------------------

------------------------------------------------------------------------------------------------------------------------
-- Verifica se chamada foi direcionada para o serviço correto
------------------------------------------------------------------------------------------------------------------------
	IF (@idloterpsxml IS NULL) OR (@numerolote IS NULL) OR (@cnpj IS NULL) OR (@inscricaomunicipal IS NULL)
	BEGIN      
		SET @MsgErro = 'Dados obrigatórios para o serviço não foram preenchidos. Verifique se o serviço chamado está correto.'	
		GOTO TrataErro
	END


-- SESSÃO ---------------------------------------------------------------------------------------------------------------
	SELECT @MsgErro = sis.fn_ConsisteUsuarioSessao(@idSessao)
	IF @MsgErro IS NOT NULL
		GOTO Fim

-- CONSISTÊNCIA DOS DADOS -----------------------------------------------------------------------------------------------
	
	-- RETIRADO POR QUE CAUSOU TIME OUT EM ARQUIVOS GRANDES (MARCELO - RAFAEL)
	--Verifica se Id (Lote e Rps) está igual ao URI (Assinatura Lote e Rps)
	
	--CREATE TABLE #Lote
	--(idlote				VARCHAR(255), 
	--URIAssinatura		VARCHAR(255),
	--lotexml				XML
	--)


	--CREATE TABLE #Rps
	--(idlote				VARCHAR(255), 
	--idRps				VARCHAR(255),
	--URIAssinatura		VARCHAR(255)
	--)

	--SELECT	@idlote = @xmlentrada.value('declare namespace n1="http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd";(//n1:LoteRps/@Id)[1]','VARCHAR(255)'),
	--		@URIassinatura = @xmlentrada.value('declare namespace n1="http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd";declare namespace n2="http://www.w3.org/2000/09/xmldsig#";(//n1:EnviarLoteRpsEnvio/n2:Signature/n2:SignedInfo/n2:Reference/@URI)[1]','VARCHAR(255)')
	
	--INSERT INTO #Lote (idlote,URIAssinatura,lotexml)
	--VALUES (@idlote,@URIassinatura,@xmlentrada)
	
	--INSERT #Rps
	--SELECT	idlote,
	--		nref.value('declare namespace n1="http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd";(../../../n1:InfRps/@Id)[1]', 'VARCHAR(255)') idrps,
	--		nref.value('declare namespace n2="http://www.w3.org/2000/09/xmldsig#";@URI', 'VARCHAR(255)') UriAssinatura
	--FROM #Lote CROSS APPLY @xmlentrada.nodes('declare namespace n1="http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd";declare namespace n2="http://www.w3.org/2000/09/xmldsig#";//n1:Rps/n2:Signature/n2:SignedInfo/n2:Reference') AS R(nref)

	--SELECT @errosassinalote=COUNT(*) FROM #Lote
	--WHERE ISNULL(idlote,'')<>ISNULL(SUBSTRING(URIAssinatura,2,len(URIAssinatura)),'') 

	--SELECT @errosassinarps=COUNT(*) FROM #Rps
	--WHERE ISNULL(idrps,'')<>ISNULL(SUBSTRING(URIAssinatura,2,len(URIAssinatura)),'')

	--IF (@errosassinalote>0)
	--BEGIN
	--	SET	@MsgErro = 'O Lote de Rps não está assinado'
	--	GOTO Fim
	--END	
	
	--IF (@errosassinarps>0)
	--BEGIN
	--	SET	@MsgErro = 'Há Rps não assinados no lote'
	--	GOTO Fim
	--END	
	
------------------------------------------------------------------------------------------------------------------------
---E142 - Inscrição Municipal do prestador não está vinculada ao CNPJ informado.
------------------------------------------------------------------------------------------------------------------------

	--Verifica se CNPJ e Inscrição Municipal são da mesma pessoa e estão cadastradas em Sis.Cadmer

	SELECT @idcadmer = idcadmer FROM Sis.Cadmer (NOLOCK)
	WHERE (numerodocumento = @cnpj) AND (inscricao = @inscricaomunicipal)
	
--	IF @idcadmer IS NULL
--		INSERT INTO @listaerros (codigoerro) VALUES ('E142')

------------------------------------------------------------------------------------------------------------------------
---Erro retirado porque o usuário do XML é interno e não há como fazer essa validação. Controle é apenas pelo certificado
------------------------------------------------------------------------------------------------------------------------

--	--Obtém idusuario da sessão
--
	SET @idusuario = dbo.fn_RetornaIdusuarioSessao(@idsessao)
--
--	--Verifica autorização do usuário
--
--	IF NOT EXISTS (
--
--	SELECT     Nfse.UsuarioAcaoEmpresaAutorizada.idusuarioacaoempresaautorizada
--	FROM       Nfse.AcoesNota (NOLOCK) INNER JOIN
--                      Nfse.UsuarioAcaoEmpresaAutorizada (NOLOCK) ON Nfse.AcoesNota.idacoesnota = Nfse.UsuarioAcaoEmpresaAutorizada.idacoesnota INNER JOIN
--                      Nfse.EmpresaAutorizada (NOLOCK) ON Nfse.UsuarioAcaoEmpresaAutorizada.idempresaautorizada = Nfse.EmpresaAutorizada.idempresaautorizada  INNER JOIN
--                      Sis.Cadmer (NOLOCK) ON Nfse.EmpresaAutorizada.idcadmer = Sis.Cadmer.idcadmer AND Nfse.EmpresaAutorizada.idcadmer = Sis.Cadmer.idcadmer 
--    WHERE		(UsuarioAcaoEmpresaAutorizada.idusuario = @idusuario) AND 
--				(Nfse.AcoesNota.codigo = 'L') AND 
--				(Sis.Cadmer.idcadmer = @idcadmer) 
--	)
--
--	BEGIN
--		INSERT INTO @listaerros (codigoerro) VALUES ('E157')
--	END

------------------------------------------------------------------------------------------------------------------------
---Falta criar erro na tabela
------------------------------------------------------------------------------------------------------------------------
	--Verifica se há dados em branco

--	IF	(@idloterpsxml IS NULL) 
--	BEGIN
--		SET	@MsgErro = 'Campo ID não pode estar em branco!'
--		GOTO Fim
--	END
	

------------------------------------------------------------------------------------------------------------------------
---E88 - Número de lote não informado => Transferido para sp_Processa_LoteRPS
------------------------------------------------------------------------------------------------------------------------

--	-- Trata Erro E88
--	IF (@numerolote IS NULL) 
--		INSERT INTO @listaerros (codigoerro) VALUES ('E88')
		
------------------------------------------------------------------------------------------------------------------------
---E149 - Campo CNPJPrestador informado incorretamente => Transferido para sp_Processa_LoteRPS
------------------------------------------------------------------------------------------------------------------------
--	
--	IF (@cnpj IS NULL)
--		INSERT INTO @listaerros (codigoerro) VALUES ('E149')
		

------------------------------------------------------------------------------------------------------------------------
---E141 - Inscrição Municipal do prestador não informada => Transferido para sp_Valida_RPS_Cadastros
------------------------------------------------------------------------------------------------------------------------
--	IF (@inscricaomunicipal IS NULL) 
--	BEGIN
--		SET	@MsgErro = 'Campo Inscrição Municipal não pode estar em branco!'
--		GOTO Fim
--	END
	
------------------------------------------------------------------------------------------------------------------------
---E151 - Quantidade de RPS não informada => Transferido para sp_Processa_Lote_RPS
------------------------------------------------------------------------------------------------------------------------
--	IF (@QuantidadeRps IS NULL)
--		INSERT INTO @listaerros (codigoerro) VALUES ('E151')
	

------------------------------------------------------------------------------------------------------------------------
---N32 - Esse Lote já foi processado com sucesso
------------------------------------------------------------------------------------------------------------------------
	--Verifica se o Lote já foi processado com sucesso

	SELECT	@idloterps				= idloterps, 
			@codigosituacaoloterps	= codigo
	FROM Nfse.LoteRps (NOLOCK) LOTE
	INNER JOIN Nfse.SituacaoLoteRps (NOLOCK) SITU ON LOTE.idsituacaoloterps = SITU.idsituacaoloterps
	WHERE	(idcadmer = @idcadmer) AND (numerolote	= @numerolote) AND 	(LOTE.idsituacaoloterps = 4)	
	
	IF (@idloterps IS NOT NULL) 
		INSERT INTO @listaerros (codigoerro) VALUES ('N32')

------------------------------------------------------------------------------------------------------------------------
---N33 - Esse Lote já está em processamento
------------------------------------------------------------------------------------------------------------------------

	SELECT	@idloterps				= idloterps, 
			@codigosituacaoloterps	= codigo
	FROM Nfse.LoteRps (NOLOCK) LOTE
	INNER JOIN Nfse.SituacaoLoteRps (NOLOCK) SITU ON LOTE.idsituacaoloterps = SITU.idsituacaoloterps
	WHERE	(idcadmer = @idcadmer) AND (numerolote	= @numerolote) AND 	((LOTE.idsituacaoloterps = 6) OR (LOTE.idsituacaoloterps = 7))	
	
	IF (@idloterps IS NOT NULL) 
		INSERT INTO @listaerros (codigoerro) VALUES ('N33')

------------------------------------------------------------------------------------------------------------------------
---E69 - Quantidade de RPS incorreta => Transferido para sp_Processa_Lote_RPS
------------------------------------------------------------------------------------------------------------------------
	
--	IF @quantidaderpscalc <> @quantidaderps
--		INSERT INTO @listaerros (codigoerro) VALUES ('E69')


------------------------------------------------------------------------------------------------------------------------
-- REGRA DE NEGÓCIO
------------------------------------------------------------------------------------------------------------------------

	IF NOT EXISTS (SELECT codigoerro FROM @listaerros)
	BEGIN
		
			
		BEGIN TRANSACTION
	
			SELECT	@idloterps				= idloterps, 
					@codigosituacaoloterps	= codigo
			FROM Nfse.LoteRps (NOLOCK) LOTE
			INNER JOIN Nfse.SituacaoLoteRps (NOLOCK) SITU ON LOTE.idsituacaoloterps = SITU.idsituacaoloterps
			WHERE	(idcadmer = @idcadmer) AND (numerolote	= @numerolote)

			IF (@idloterps IS NOT NULL AND @codigosituacaoloterps = 2)
			BEGIN
				
				--Obtem o idsituacaoloterps para o código 5 - cancelado

				SELECT @idsituacaoloterpsgravar = idsituacaoloterps
				FROM Nfse.SituacaoLoteRps (NOLOCK)
				WHERE codigo = 5

				IF @idsituacaoloterpsgravar IS NULL
				BEGIN
					SET	@MsgErro = 'Tabela de Situação de Lote Rps está incompleta'
					GOTO TrataErro
				END

				UPDATE	Nfse.LoteRps
				SET		idsituacaoloterps = @idsituacaoloterpsgravar,
						idsessao		  = @idsessao,
						usuarioinclusao	  = @idusuario
				WHERE	idloterps		  = @idloterps

			END	


			IF  (@idloterps IS NULL) OR (@idloterps IS NOT NULL AND @codigosituacaoloterps <> 4 )
			BEGIN

				--Obtem o idsituacaoloterps para o código 2 - Lote não processado

				SELECT @idsituacaoloterpsgravar = idsituacaoloterps
				FROM Nfse.SituacaoLoteRps (NOLOCK)
				WHERE codigo = 2

				IF @idsituacaoloterpsgravar IS NULL
				BEGIN
					SET	@MsgErro = 'Tabela de Situação de Lote Rps está incompleta'
					GOTO TrataErro
				END

				INSERT INTO Nfse.LoteRps
				
						(idsituacaoloterps, 
						idcadmer, 
						numerolote, 
						numeroprotocolo,
						quantidaderps, 
						lotexml, 
						idsessao, 
						usuarioinclusao, 
						datainclusao)
					
				VALUES (@idsituacaoloterpsgravar,
						@idcadmer,
						@numerolote,
						CONVERT(VARCHAR(8),CONVERT(INT,RAND()*100000000)),
						@quantidadeRps,
						CONVERT(VARCHAR(MAX),@xmlentrada),
						@idsessao,
						@idusuario,
						GETDATE()
						)
				
				SET @idloterps = SCOPE_IDENTITY()
										
			END
	
		COMMIT TRANSACTION

		;WITH XMLNAMESPACES(DEFAULT 'http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd')
		SELECT	@xmlretorno = (
		SELECT	NumeroLote											"NumeroLote",
				REPLACE (convert(varchar, getdate(), 120),' ','T')	"DataRecebimento",
				Numeroprotocolo										"Protocolo"
		FROM Nfse.Loterps (NOLOCK)
		WHERE idloterps = @idloterps
		FOR XML PATH('EnviarLoteRpsResposta'))
		
			

	END
	ELSE
	BEGIN

		;WITH XMLNAMESPACES(DEFAULT 'http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd')
		SELECT @xml = 
		(
		SELECT 
		T1.codigoerro	"Codigo",
		T2.mensagem		"Mensagem",
		T2.solucao		"Correcao"
		FROM
		@listaerros T1 LEFT JOIN Nfse.Erroalerta T2 ON T1.codigoerro = T2.codigo
		FOR XML PATH('MensagemRetorno'),ROOT('ListaMensagemRetorno')
		)

		;WITH XMLNAMESPACES(DEFAULT 'http://www.abrasf.org.br/ABRASF/arquivos/nfse.xsd')
		SELECT @xmlretorno = (SELECT @xml FOR XML PATH ('EnviarLoteRpsResposta'))


	END
	
	--SELECT numeroprotocolo FROM Nfse.LoteRps WHERE idloterps = @idloterps	

	
	RETURN 0	

TrataErro:
	IF @@TRANCOUNT > 0 -- Se houver transação na procedure
		ROLLBACK TRANSACTION
Fim:
	RAISERROR 13000 @MsgErro
	RETURN 1

END




