const kb_settings = window.wc.wcSettings.getSetting('kinabank_data', {});
const kb_title = window.wp.htmlEntities.decodeEntities(kb_settings.title);

const kb_content = () => {
    return window.wp.htmlEntities.decodeEntities(kb_settings.description || '');
};

const kb_label = () => {
    let icon = kb_settings.icon
        ? window.wp.element.createElement(
            'img',
            {
                alt: kb_title,
                title: kb_title,
                src: kb_settings.icon,
                style: { float: 'right', paddingRight: '1em' }
            }
        )
        : null;

    let label = window.wp.element.createElement(
        'span',
        icon ? { style: { width: '100%' } } : null,
        kb_title,
        icon
    );

    return label;
};

const kb_blockGateway = {
    name: kb_settings.id,
    label: Object(window.wp.element.createElement)(kb_label, null),
    icons: ['visa', 'mastercard'],
    content: Object(window.wp.element.createElement)(kb_content, null),
    edit: Object(window.wp.element.createElement)(kb_content, null),
    canMakePayment: () => true,
    ariaLabel: kb_title,
    supports: {
        features: kb_settings.supports,
    },
};

window.wc.wcBlocksRegistry.registerPaymentMethod(kb_blockGateway);
