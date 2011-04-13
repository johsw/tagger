Dette er en alpha-version af tagger-projektet.

Det er en REST webservice og den originale kode er i høj grad inspireret af to
artikler af Ian Selby:
http://www.gen-x-design.com/archives/create-a-rest-api-with-php
http://www.gen-x-design.com/archives/making-restful-requests-in-php

Lige nu leder den efter steder, personer og organisioner. På sigt vil den også bruge en anden algoritme der kan finde mere generelle emneord som f.eks 'valg' eller 'demokrati'.

Både GET og POST kan bruges til forespørgsler. 
Argumenter:
 * Det vigtigste argument den tager er 'text'. Som f.eks her:
http://yourdomain.dk/v1/tag?text=Jeg%20har%20en%20gang%20v%C3%A6ret%20i%20%C3%85rhus%20og%20h%C3%B8re%20musik
Tekst er det stykke tekst du vil have analyseret.
