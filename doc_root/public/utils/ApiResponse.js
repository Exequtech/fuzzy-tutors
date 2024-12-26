class ApiResponse {
    constructor(isSuccessful = false, message = '', data = null) {
        this.isSuccessful = isSuccessful;
        this.message = message;
        this.data = data;
    }

    static success(message = 'Operation successful', data = null) {
        return new ApiResponse(true, message, data);
    }

    static error(message = 'Operation failed', data = null) {
        return new ApiResponse(false, message, data);
    }
}

export {ApiResponse};