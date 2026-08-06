/**
 * "PET" o "PETG" en el nombre de un producto: el envase es de plástico.
 *
 * Se busca como palabra suelta y no como pedazo de texto. De los once productos
 * del catálogo que tienen "pet" adentro, sólo seis son envase; los otros cinco
 * son "MCT premium petaca" y cuatro con "pétalos de rosa".
 *
 * Sin lookbehind aunque sería más prolijo: Safari recién lo soporta desde 2023,
 * y una expresión regular que el navegador no entiende no rompe esta línea,
 * rompe el archivo entero y con él toda la tienda. \b alcanza y anda en todos.
 *
 * Vive acá y no adentro de un componente porque lo usan la tarjeta y la ventana
 * de detalle: con dos copias, el día que aparezca un envase nuevo una queda
 * vieja y el mismo producto se marca distinto según dónde se lo mire.
 *
 * @param {string|null|undefined} nombre
 * @returns {string|null} "PET", "PETG", o null si no corresponde
 */
export function envasePet(nombre) {
    return nombre?.match(/\bpetg?\b/i)?.[0].toUpperCase() ?? null;
}
