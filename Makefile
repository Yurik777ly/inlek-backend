docker-up:
	docker compose up -d --build

docker-down:
	docker compose down

docker-shell:
	docker exec -it inlek-php bash
