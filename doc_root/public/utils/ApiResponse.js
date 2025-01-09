class ApiResponse {
    constructor(isSuccessful = false, message = '', data = null, code = 200) {
        this.isSuccessful = isSuccessful;
        this.message = message;
        this.data = data;
        this.code = code;
    }

    static success(message = 'Operation successful', data = null, code = 200) {
        return new ApiResponse(true, message, data, code);
    }

    static error(message = 'Operation failed', data = null, code = null) {
        return new ApiResponse(false, message, data, code);
    }
}

export {ApiResponse};