# Datos personales y privacidad

## Por qué este documento existe

Un sitio parroquial parece inofensivo hasta que se mira lo que realmente maneja: nombres,
fechas de nacimiento, domicilios y teléfonos de niños que se preparan para su primera
comunión o su confirmación, y de sus padres. Son datos personales de **menores de edad**,
la categoría que la Ley Federal de Protección de Datos Personales en Posesión de los
Particulares (LFPDPPP) trata con más cuidado.

Las reglas de este documento **no son recomendaciones**. Son requisitos del sistema, y
están implementadas en el código. Cualquiera que agregue una vista pública que toque
solicitudes, inscripciones o fotografías debe leer esto antes.

## Aviso de privacidad

Vive en la tabla `paginas` con el slug `aviso-de-privacidad`, y está enlazado desde el
footer de todas las páginas y desde cada formulario.

Debe ser un **aviso integral**, es decir, contener:

- Identidad y domicilio del responsable, que es la parroquia.
- Finalidades del tratamiento, con las **primarias separadas de las secundarias**.
- Qué datos se recaban.
- Transferencias a terceros, si las hubiera. Idealmente ninguna.
- Los medios para ejercer los **derechos ARCO** —acceso, rectificación, cancelación y
  oposición—: un correo electrónico y el domicilio de la oficina parroquial.
- Mención expresa del tratamiento de datos de menores y del consentimiento del padre,
  madre o tutor.
- El procedimiento para revocar el consentimiento.

## Consentimiento

### Versionado

El aviso de privacidad cambiará con el tiempo. Sin registrar qué versión aceptó cada
persona, no hay forma de demostrar a qué dio su consentimiento.

Por eso existe la constante `AVISO_VERSION` en `config/app.php`, espejada en la clave
`aviso_privacidad_version` de la tabla `configuracion`. Cada registro de
`solicitudes_sacramento`, `inscripciones_curso` y `mensajes_contacto` guarda tres columnas:

| Columna | Qué guarda |
|---|---|
| `consentimiento` | Que la persona marcó la casilla |
| `consentimiento_ip` | Desde dónde |
| `aviso_version` | Qué texto exacto aceptó |

Al publicar una versión nueva del aviso se incrementa la constante. Los consentimientos
anteriores quedan asociados a su versión.

### La casilla

Está en el parcial reutilizable `shared/views/parciales/consentimiento_privacidad.php`:

- **Nunca premarcada.** Un consentimiento premarcado no es consentimiento.
- Texto: *"He leído y acepto el Aviso de Privacidad"*, con enlace que abre en una pestaña
  nueva para no perder lo que la persona ya escribió.
- **Validada en el servidor.** Si no viene marcada, no se inserta el registro. La
  validación en JavaScript es cortesía para el usuario, no un control.

## Menores de edad

Si la fecha de nacimiento capturada implica menos de 18 años:

1. El servidor pone `es_menor = 1`. **Se calcula en el servidor**, nunca se confía en lo
   que mande el formulario.
2. Los campos `tutor_nombre`, `tutor_parentesco` y `tutor_telefono` pasan a ser
   obligatorios, validados en el servidor y no solo en el navegador.
3. El texto del consentimiento cambia a *"Como padre, madre o tutor, autorizo el
   tratamiento de los datos personales del menor…"*.

## Minimización

Solo se pide lo que hace falta para el trámite.

**No se pide** CURP, RFC, número de seguridad social, ni ningún dato sensible en el
sentido de la ley: origen étnico, estado de salud, creencias distintas a la propia
participación sacramental, preferencias sexuales u opiniones políticas.

Si el registro sacramental exigiera un dato adicional, se agrega mediante
`sacramento_campos` marcándolo con `dato_sensible = 1`. Esos campos solo se muestran a los
roles de administrador y secretaría.

## Qué nunca se publica

Esta es la lista corta, y se aplica en el código, no solo en el criterio de quien
administre:

**Nombres, fotografías ni datos de menores en el sitio público.** La tabla
`galeria_imagenes` tiene la columna `autorizacion_imagen`, que arranca en 0, y la consulta
pública filtra `WHERE publicada = 1 AND autorizacion_imagen = 1`. Una fotografía sin
autorización registrada no puede llegar al sitio ni por descuido.

**Listas de inscritos, solicitudes o folios.** Viven exclusivamente en el panel
autenticado. No hay ni habrá una vista pública de "quiénes se inscribieron".

**Domicilios y teléfonos particulares** de feligreses o del equipo pastoral. En la tabla
`personas` se publica únicamente el correo institucional.

## Control de acceso

| Rol | Ve datos personales |
|---|---|
| Administrador | Sí |
| Secretaría | Sí |
| Editor | No |
| Coordinador | **No**, ni siquiera de su propia pastoral |

El rol de secretaría existe precisamente para esto: separar a quien administra trámites de
quien edita la web. Un coordinador de pastoral juvenil publica avisos y sube fotos de sus
actividades, pero no tiene por qué ver las actas de nacimiento de los niños de catequesis.

## Trazabilidad

La función `auditoria()` se llama **también en la lectura** de datos personales, no solo
al escribirlos:

- Al abrir el listado de solicitudes o de inscripciones: `accion = 'consultar'`.
- Al abrir el detalle de una solicitud.
- En **cada exportación a CSV**, que es el punto por donde los datos salen del sistema.

Es lo que permite responder con hechos si alguien pregunta quién consultó su información.

## Retención

Los datos no se conservan indefinidamente. La clave `retencion_meses_solicitudes` de la
tabla `configuracion` fija el plazo, con 36 meses por defecto.

La acción `solicitudes.purgar` del panel **anonimiza, no borra**, los registros vencidos
que ya estén en estado `completada` o `rechazada`:

- Se vacían `nombre_solicitante`, `telefono`, `email`, `direccion`, los campos de tutor y
  `datos_extra`.
- Se conservan `folio`, `sacramento_id`, `estado` y las fechas, para poder seguir
  contando cuántos bautizos hubo en un año.

La operación queda registrada en la auditoría.

## Transporte

En producción, HTTPS obligatorio: redirección forzada y cabecera HSTS en `.htaccess`. La
clase `Session` ya marca la cookie como `secure` cuando detecta HTTPS.

Un formulario con datos de menores viajando en texto plano no es aceptable, y hoy no hay
excusa: los certificados son gratuitos y cPanel los emite en dos clics.

## Checklist antes de publicar el sitio

- [ ] El aviso de privacidad está redactado, revisado y publicado.
- [ ] `AVISO_VERSION` y la clave en `configuracion` coinciden.
- [ ] Todos los formularios públicos muestran la casilla de consentimiento sin premarcar.
- [ ] Un envío sin consentimiento se rechaza del lado del servidor.
- [ ] Una fecha de nacimiento de menor exige los datos del tutor.
- [ ] La galería pública no muestra imágenes sin `autorizacion_imagen`.
- [ ] Ninguna URL pública devuelve datos de solicitudes o inscripciones.
- [ ] Un usuario con rol coordinador no puede abrir el módulo de solicitudes.
- [ ] La auditoría registra la consulta y la exportación de datos personales.
- [ ] HTTPS está activo y forzado.
