export const useCurrency = () => {
    const formatCurrency = (value) => {
        const numeric = Number(value ?? 0);

        return new Intl.NumberFormat('th-TH', {
            style: 'currency',
            currency: 'THB',
            minimumFractionDigits: 2,
        }).format(Number.isNaN(numeric) ? 0 : numeric);
    };

    return { formatCurrency };
};
