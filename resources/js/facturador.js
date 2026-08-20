import "bootstrap";
import Alpine from "alpinejs";
import {
    ArrowRight,
    Check,
    CirclePlus,
    createIcons,
    Eye,
    Files,
    Hash,
    House,
    LogOut,
    Menu,
    PackageSearch,
    Plus,
    Printer,
    Receipt,
    ReceiptText,
    Search,
    Send,
    Share2,
    UserCog,
    UserRound,
    UsersRound,
} from "lucide";

window.Alpine = Alpine;

Alpine.data("ventaRapida", (config) => ({
    series: config.series || {},
    tipo: config.tipo || "BOLETA",
    serie: config.serie || "",
    receptorTipo: config.receptorTipo || "SIN_DOCUMENTO",
    receptorNumero: config.receptorNumero || "",
    receptorNombre: config.receptorNombre || "Cliente varios",
    buscarCliente: "",
    clientes: [],
    buscandoClientes: false,
    buscarProducto: "",
    productos: [],
    buscandoProductos: false,
    items: config.items?.length
        ? config.items
        : [
              {
                  descripcion: "",
                  unidad_medida: "NIU",
                  cantidad: 1,
                  valor_unitario: "",
                  descuento: "",
                  codigo_producto: "",
              },
          ],
    temporizadorCliente: null,
    temporizadorProducto: null,

    init() {
        this.ajustarSerie();
    },

    documentos() {
        return this.tipo === "FACTURA"
            ? [{ value: "RUC", label: "RUC" }]
            : [
                  { value: "SIN_DOCUMENTO", label: "Sin documento" },
                  { value: "DNI", label: "DNI" },
                  {
                      value: "CARNET_EXTRANJERIA",
                      label: "Carnet de extranjería",
                  },
                  { value: "PASAPORTE", label: "Pasaporte" },
              ];
    },

    cambiarTipo(tipo) {
        this.tipo = tipo;
        this.receptorTipo = tipo === "FACTURA" ? "RUC" : "SIN_DOCUMENTO";
        this.receptorNumero = "";
        this.receptorNombre = tipo === "FACTURA" ? "" : "Cliente varios";
        this.buscarCliente = "";
        this.clientes = [];
        this.ajustarSerie();
    },

    ajustarSerie() {
        const disponibles = this.series[this.tipo] || [];
        if (!disponibles.includes(this.serie))
            this.serie = disponibles[0] || "";
    },

    buscarClientes() {
        clearTimeout(this.temporizadorCliente);
        if (this.buscarCliente.trim().length < 2) {
            this.clientes = [];
            return;
        }
        this.temporizadorCliente = setTimeout(async () => {
            this.buscandoClientes = true;
            try {
                const respuesta = await fetch(
                    `${config.clientesUrl}?tipo=${this.tipo}&q=${encodeURIComponent(this.buscarCliente)}`,
                );
                this.clientes = respuesta.ok ? await respuesta.json() : [];
            } finally {
                this.buscandoClientes = false;
            }
        }, 220);
    },

    elegirCliente(cliente) {
        this.receptorTipo = cliente.tipo_documento;
        this.receptorNumero = cliente.numero_documento;
        this.receptorNombre = cliente.nombre;
        this.buscarCliente = `${cliente.nombre} · ${cliente.numero_documento}`;
        this.clientes = [];
    },

    buscarProductos() {
        clearTimeout(this.temporizadorProducto);
        if (this.buscarProducto.trim().length < 2) {
            this.productos = [];
            return;
        }
        this.temporizadorProducto = setTimeout(async () => {
            this.buscandoProductos = true;
            try {
                const respuesta = await fetch(
                    `${config.productosUrl}?q=${encodeURIComponent(this.buscarProducto)}`,
                );
                this.productos = respuesta.ok ? await respuesta.json() : [];
            } finally {
                this.buscandoProductos = false;
            }
        }, 220);
    },

    agregarProducto(producto) {
        const vacio = this.items.find((item) => !item.descripcion.trim());
        const item = vacio || this.nuevoItem();
        item.descripcion = producto.nombre;
        item.unidad_medida = producto.unidad_medida;
        item.valor_unitario = producto.valor_unitario;
        item.codigo_producto = producto.codigo || "";
        if (!vacio) this.items.push(item);
        this.buscarProducto = "";
        this.productos = [];
    },

    agregarManual() {
        this.items.push(this.nuevoItem());
    },

    nuevoItem() {
        return {
            descripcion: "",
            unidad_medida: "NIU",
            cantidad: 1,
            valor_unitario: "",
            descuento: "",
            codigo_producto: "",
        };
    },

    eliminarItem(indice) {
        if (this.items.length > 1) this.items.splice(indice, 1);
    },

    baseItem(item) {
        return Math.max(
            0,
            (Number(item.cantidad) || 0) * (Number(item.valor_unitario) || 0) -
                (Number(item.descuento) || 0),
        );
    },

    get subtotal() {
        return this.items.reduce(
            (total, item) => total + this.baseItem(item),
            0,
        );
    },

    get igv() {
        return this.subtotal * 0.18;
    },

    get total() {
        return this.subtotal + this.igv;
    },

    dinero(valor) {
        return new Intl.NumberFormat("es-PE", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(valor || 0);
    },
}));

Alpine.data("seguimientoVenta", (url, inicial) => ({
    estado: inicial,
    etiqueta: "Preparando envío",
    terminal: false,
    intentos: 0,

    init() {
        this.etiqueta = this.etiquetaEstado(this.estado);
        this.terminal = [
            "ACEPTADO",
            "ACEPTADO_CON_OBSERVACIONES",
            "RECHAZADO",
            "ERROR",
        ].includes(this.estado);
        if (!this.terminal) this.consultar();
    },

    async consultar() {
        if (this.terminal || this.intentos >= 20) return;
        await new Promise((resolve) => setTimeout(resolve, 3000));
        this.intentos++;
        try {
            const respuesta = await fetch(url, {
                headers: { Accept: "application/json" },
            });
            if (respuesta.ok) {
                const datos = await respuesta.json();
                this.estado = datos.estado;
                this.etiqueta = datos.etiqueta;
                this.terminal = datos.terminal;
            }
        } finally {
            if (!this.terminal) this.consultar();
        }
    },

    etiquetaEstado(estado) {
        return (
            {
                REGISTRADO: "Preparando envío",
                PROCESANDO: "Enviando a SUNAT",
                ACEPTADO: "Aceptado por SUNAT",
                ACEPTADO_CON_OBSERVACIONES: "Aceptado con observaciones",
                RECHAZADO: "SUNAT no aceptó el comprobante",
                ERROR: "No se pudo completar el envío",
            }[estado] || "Procesando"
        );
    },
}));

Alpine.start();

const facturadorIcons = {
    ArrowRight,
    Check,
    CirclePlus,
    Eye,
    Files,
    Hash,
    House,
    LogOut,
    Menu,
    PackageSearch,
    Plus,
    Printer,
    Receipt,
    ReceiptText,
    Search,
    Send,
    Share2,
    UserCog,
    UserRound,
    UsersRound,
};

const renderIcons = () =>
    createIcons({ icons: facturadorIcons, attrs: { "stroke-width": 1.8 } });
document.addEventListener("DOMContentLoaded", renderIcons);
document.addEventListener("alpine:initialized", renderIcons);
