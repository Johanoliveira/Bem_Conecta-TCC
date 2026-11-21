
document.addEventListener("DOMContentLoaded", () => {
    const formulario = document.querySelector("form");
    const campoImagem = document.getElementById("imagem");
    const telaUpload = document.getElementById("upload-inicial");
    const botaoEnviar = document.getElementById("botao-enviar-imagem");

    const editor = document.getElementById("editor-imagem");
    const imagemRecorte = document.getElementById("imagem-recorte");
    const fundoModal = document.getElementById("fundo-modal");
    const fecharEditor = document.getElementById("fechar-editor");

    const botaoMenos = document.getElementById("zoom-menos");
    const botaoMais = document.getElementById("zoom-mais");
    const controleZoom = document.getElementById("controle-zoom");
    const botaoCancelar = document.getElementById("cancelar-recorte");
    const botaoConfirmar = document.getElementById("confirmar-recorte");

    const telaPronta = document.getElementById("imagem-pronta");
    const previewPronto = document.getElementById("preview-pronto");
    const botaoMudar = document.getElementById("mudar-imagem");
    const botaoEditar = document.getElementById("editar-imagem");
    const botaoPublicar = formulario.querySelector(".publish-btn");

    // O arquivo original nunca é substituído pelo recorte.
    let arquivoOriginal = null;
    let arquivoConfirmado = null;

    // Estado anterior, usado para cancelar uma troca ou edição.
    let originalAnterior = null;
    let confirmadoAnterior = null;

    let cropper = null;
    let urlOriginal = null;
    let urlPronta = null;
    let zoomAnterior = 0;
    let processando = false;
    let geracaoEditor = 0;

    const tiposPermitidos = [
        "image/jpeg",
        "image/png",
        "image/webp"
    ];

    function destruirCropper() {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
    }

    function limparUrlOriginal() {
        if (urlOriginal) {
            URL.revokeObjectURL(urlOriginal);
            urlOriginal = null;
        }
    }

    function atualizarArquivoDoFormulario(arquivo) {
        const transferencia = new DataTransfer();

        if (arquivo) {
            transferencia.items.add(arquivo);
        }

        campoImagem.files = transferencia.files;
    }

    function salvarEstadoAnterior() {
        originalAnterior = arquivoOriginal;
        confirmadoAnterior = arquivoConfirmado;
    }

    function mostrarUpload() {
        telaUpload.hidden = false;
        editor.hidden = true;
        telaPronta.hidden = true;
    }

    function mostrarEditor() {
        telaUpload.hidden = true;
        telaPronta.hidden = true;
        editor.hidden = false;

        document.body.style.overflow = "hidden";
    }

    function mostrarImagemPronta() {
        telaUpload.hidden = true;
        editor.hidden = true;
        telaPronta.hidden = false;

        document.body.style.overflow = "";
    }

    function abrirEditor(arquivo) {
        destruirCropper();
        limparUrlOriginal();

        const geracaoAtual = ++geracaoEditor;
        urlOriginal = URL.createObjectURL(arquivo);

        imagemRecorte.onload = () => {
            if (geracaoAtual !== geracaoEditor) return;

            destruirCropper();

            cropper = new Cropper(imagemRecorte, {
                aspectRatio: 16 / 9,
                viewMode: 1,
                dragMode: "move",
                autoCropArea: 1,
                responsive: true,
                restore: false,
                guides: true,
                center: true,
                highlight: false,
                background: false,
                movable: true,
                zoomable: true,
                zoomOnWheel: true,
                zoomOnTouch: true,
                cropBoxMovable: false,
                cropBoxResizable: false,
                toggleDragModeOnDblclick: false,

                ready() {
                    controleZoom.value = 0;
                    zoomAnterior = 0;
                }
            });
        };

        imagemRecorte.onerror = () => {
            if (geracaoAtual !== geracaoEditor) return;

            alert("Não foi possível abrir essa imagem.");
            cancelarEdicao();
        };

        imagemRecorte.src = urlOriginal;
        mostrarEditor();
    }

    function abrirSeletorImagem() {
        salvarEstadoAnterior();

        // Limpa o campo para permitir escolher o mesmo arquivo novamente.
        campoImagem.value = "";
        campoImagem.click();
    }

    function cancelarEdicao() {
        if (processando) return;

        geracaoEditor++;
        destruirCropper();
        limparUrlOriginal();

        arquivoOriginal = originalAnterior;
        arquivoConfirmado = confirmadoAnterior;

        atualizarArquivoDoFormulario(arquivoConfirmado);

        if (arquivoConfirmado && urlPronta) {
            previewPronto.src = urlPronta;
            mostrarImagemPronta();
        } else {
            mostrarUpload();
        }

        document.body.style.overflow = "";
    }

    botaoEnviar.addEventListener("click", abrirSeletorImagem);
    botaoMudar.addEventListener("click", abrirSeletorImagem);

    campoImagem.addEventListener("change", () => {
        const arquivo = campoImagem.files[0];

        // Se o seletor foi cancelado, não altera a imagem atual.
        if (!arquivo) {
            atualizarArquivoDoFormulario(arquivoConfirmado);
            return;
        }

        if (!tiposPermitidos.includes(arquivo.type)) {
            alert("Selecione uma imagem JPG, PNG ou WEBP.");
            cancelarEdicao();
            return;
        }

        if (arquivo.size > 5 * 1024 * 1024) {
            alert("A imagem original deve ter no máximo 5 MB.");
            cancelarEdicao();
            return;
        }

        // Guarda o arquivo completo, não o recorte anterior.
        arquivoOriginal = arquivo;
        abrirEditor(arquivoOriginal);
    });

    botaoEditar.addEventListener("click", () => {
        if (!arquivoOriginal) {
            alert("Selecione uma imagem primeiro.");
            return;
        }

        // Salva o estado atual para permitir cancelar a edição.
        salvarEstadoAnterior();

        // Reabre sempre a imagem original em resolução completa.
        abrirEditor(arquivoOriginal);
    });

    botaoMenos.addEventListener("click", () => {
        if (cropper) cropper.zoom(-0.1);
    });

    botaoMais.addEventListener("click", () => {
        if (cropper) cropper.zoom(0.1);
    });

    controleZoom.addEventListener("input", () => {
        if (!cropper) return;

        const valorAtual = Number(controleZoom.value);
        cropper.zoom((valorAtual - zoomAnterior) * 0.01);
        zoomAnterior = valorAtual;
    });

    botaoCancelar.addEventListener("click", cancelarEdicao);
    fecharEditor.addEventListener("click", cancelarEdicao);
    fundoModal.addEventListener("click", cancelarEdicao);

    document.addEventListener("keydown", evento => {
        if (evento.key === "Escape" && !editor.hidden) {
            cancelarEdicao();
        }
    });

    botaoConfirmar.addEventListener("click", async () => {
        if (!cropper || processando) return;

        processando = true;
        botaoConfirmar.disabled = true;
        botaoConfirmar.textContent = "Preparando imagem...";

        try {
            const canvas = cropper.getCroppedCanvas({
                width: 1200,
                height: 675,
                fillColor: "#ffffff",
                imageSmoothingEnabled: true,
                imageSmoothingQuality: "high"
            });

            if (!canvas) {
                throw new Error("Não foi possível recortar a imagem.");
            }

            const blob = await new Promise((resolve, reject) => {
                canvas.toBlob(
                    resultado => resultado
                        ? resolve(resultado)
                        : reject(new Error("Falha ao processar a imagem.")),
                    "image/jpeg",
                    0.88
                );
            });

            if (blob.size > 5 * 1024 * 1024) {
                throw new Error("A imagem final ultrapassou 5 MB.");
            }

            const novoRecorte = new File(
                [blob],
                "publicacao.jpg",
                { type: "image/jpeg" }
            );

            // Atualiza apenas a imagem confirmada.
            // arquivoOriginal continua intacto para edições futuras.
            arquivoConfirmado = novoRecorte;
            atualizarArquivoDoFormulario(arquivoConfirmado);

            if (urlPronta) {
                URL.revokeObjectURL(urlPronta);
            }

            urlPronta = URL.createObjectURL(arquivoConfirmado);
            previewPronto.src = urlPronta;

            destruirCropper();
            limparUrlOriginal();

            mostrarImagemPronta();
            document.body.style.overflow = "";

        } catch (erro) {
            alert(erro.message || "Não foi possível preparar a imagem.");
        } finally {
            processando = false;
            botaoConfirmar.disabled = false;
            botaoConfirmar.textContent = "Confirmar imagem";
        }
    });

    formulario.addEventListener("submit", evento => {
        if (!arquivoConfirmado) {
            evento.preventDefault();
            alert("Selecione e confirme uma imagem antes de publicar.");
            return;
        }

        // Garante que o PHP receba o recorte confirmado.
        atualizarArquivoDoFormulario(arquivoConfirmado);
    });

    window.addEventListener("beforeunload", () => {
        destruirCropper();
        limparUrlOriginal();

        if (urlPronta) {
            URL.revokeObjectURL(urlPronta);
        }
    });
});
