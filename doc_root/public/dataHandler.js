let response = {
    isSuccessfull: false,
    message: ""
}

async function registerUser(username, userType, email, password) {
    let body = {
        username: username,
        userType: userType,
        email: email,
        password: password
    }
    let apiResponse = await fetch("/api/auth/signup",
        {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(body)
        }).then(apiResponse => apiResponse.json())
        .catch((error) => console.log(error.message));
    if(apiResponse.status == 200)
    {
        response.isSuccessfull = true;
        response.message = apiResponse.detail;
        return response;
    } else {
        response.isSuccessfull = false;
        response.message = apiResponse.detail;
        return response;
    }
}

async function loginUser(usernameOrEmail, isEmail, password) {
    let body = {
        password: password
    };

    if (isEmail) {
        body.email = emailOrUsername;
    } else {
        body.username = usernameOrEmail;
    }

    let apiResponse = await fetch("/api/auth/login",
        {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(body)
        }).then(apiResponse => apiResponse.json())
        .catch((error) => console.log(error.message));
    if(apiResponse.status == 200)
    {
        response.isSuccessfull = true;
        response.message = apiResponse.detail;
        return response;
    }
    else
    {
        response.isSuccessfull = false;
        response.message = apiResponse.detail;
        return response;
    }
}