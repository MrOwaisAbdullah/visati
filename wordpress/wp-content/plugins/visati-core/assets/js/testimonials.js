/**
 * Testimonial slider — prev/next arrow navigation.
 */
(function () {
  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".visati-testimonials-wrapper").forEach(function (wrapper) {
      var track = wrapper.querySelector(".visati-testimonials-track");
      var prev = wrapper.querySelector(".visati-testimonials-arrow--prev");
      var next = wrapper.querySelector(".visati-testimonials-arrow--next");
      if (!track || !prev || !next) return;

      var scrollAmount = 340;

      prev.addEventListener("click", function () {
        track.scrollBy({ left: -scrollAmount, behavior: "smooth" });
      });

      next.addEventListener("click", function () {
        track.scrollBy({ left: scrollAmount, behavior: "smooth" });
      });

      function updateArrows() {
        prev.style.opacity = track.scrollLeft <= 5 ? "0.3" : "1";
        prev.style.pointerEvents = track.scrollLeft <= 5 ? "none" : "auto";
        var maxScroll = track.scrollWidth - track.clientWidth;
        next.style.opacity = track.scrollLeft >= maxScroll - 5 ? "0.3" : "1";
        next.style.pointerEvents = track.scrollLeft >= maxScroll - 5 ? "none" : "auto";
      }

      track.addEventListener("scroll", updateArrows);
      updateArrows();
    });
  });
})();
