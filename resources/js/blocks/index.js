import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';

const PAYOUT_GATEWAY_ID = 'payout_gateway';

const settings = getSetting(`${PAYOUT_GATEWAY_ID}_data`, {});

const defaultLabel = 'Payout';
const label = decodeEntities(settings.title) || defaultLabel;

const Content = () => {
    return decodeEntities(settings.description || '');
};

const Label = (props) => {
    const { PaymentMethodLabel } = props.components;
    return <PaymentMethodLabel text={label} />;
};

const Payout = {
    name: PAYOUT_GATEWAY_ID,
    label: <Label />,
    content: <Content />,
    edit: <Content />,
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports
    }
};

registerPaymentMethod(Payout);
