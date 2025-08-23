import { notification } from 'antd';

export const toastNotification = (type, message, description) => {
    notification[type]({
        message: message,
        description: description,
        placement: 'topRight',
        showProgress: true,
    });
};