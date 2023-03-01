async function paymentConfirmed(url){
    try {
        document.querySelector(".payment-pix.sucess-detalhes-pix").style.display = "none";
        let html_success = document.querySelector(".payment-pix-paid");
        html_success.innerHTML = '<div class="lds-dual-ring"></div>';
        const data = await fetch(url);
        let res = await data.json();
        if(res.response){
            html_success.innerHTML = `
            <div class="success">
                <img src="${res.img}">
            </div>
            <div class="comment">
                <span>${res.message}</span>
            </div>`;
        }
        
    } catch (error) {
        console.error(error);
    }
}