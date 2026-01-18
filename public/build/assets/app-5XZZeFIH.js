document.addEventListener("livewire:init",()=>{Livewire.hook("request",({fail:e})=>{e(({status:i,preventDefault:o})=>{i===419&&(o(),window.location.reload())})})});
